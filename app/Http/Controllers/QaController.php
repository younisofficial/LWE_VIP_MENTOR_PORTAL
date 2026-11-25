<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\QaAnswer;
use App\Models\QaQuestion;
use App\Models\User;
use App\Notifications\QaActivity;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\EngagementAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Q&A board, one per batch. Every student and mentor of a batch sees and answers every question
 * in it, whatever course the question is about. Older questions with no batch stay per course.
 * Images are kept on the private disk and only shown to people who can see the question.
 */
class QaController extends Controller
{
    private function canSee(User $user, QaQuestion $q): bool
    {
        if ($q->batch_id) {
            $ids = $user->boardBatchIds();

            return $ids === null || in_array((int) $q->batch_id, $ids, true);
        }

        return $q->course_id ? $user->canAccessCourse($q->course_id) : $user->isStaff();
    }

    /** Pin, close, delete others' posts, mark staff answers: admins and the batch/course mentors. */
    private function canModerate(User $user, QaQuestion $q): bool
    {
        if ($q->batch_id && $user->canManageBatch((int) $q->batch_id)) {
            return true;
        }

        return $q->course_id ? $user->canManageCourse($q->course_id) : $user->isStaff();
    }

    private function guard(User $user, QaQuestion $q): void
    {
        abort_unless($this->canSee($user, $q), 404);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $batchIds = $user->boardBatchIds();
        $courseIds = $user->manageableCourseIds() === null ? null
            : array_values(array_unique([...$user->manageableCourseIds(), ...$user->learningCourseIds()]));

        $questions = QaQuestion::query()
            ->when($batchIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('batch_id', $batchIds)
                ->orWhere(fn ($old) => $old->whereNull('batch_id')->whereIn('course_id', $courseIds ?? []))))
            ->with(['batch:id,name', 'course:id,title', 'user:id,name', 'lesson:id,title'])
            ->when($request->batch_id, fn ($q, $b) => $q->where('batch_id', $b))
            ->when($request->filter === 'unanswered', fn ($q) => $q->where('status', 'open')->where('answers_count', 0))
            ->when($request->filter === 'mine', fn ($q) => $q->where('user_id', $user->id))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('body', 'like', "%{$s}%")))
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (QaQuestion $q) => [
                ...$q->only('id', 'title', 'status', 'is_pinned', 'answers_count'),
                'excerpt' => mb_substr((string) $q->body, 0, 160),
                'batch' => $q->batch?->name,
                'course' => $q->course?->title,
                'lesson' => $q->lesson?->title,
                'author' => $q->user->name,
                'has_images' => ! empty($q->images),
                'ago' => $q->last_activity_at?->diffForHumans(),
            ]);

        $batches = Batch::query()
            ->when($batchIds !== null, fn ($q) => $q->whereIn('id', $batchIds))
            ->when($batchIds === null, fn ($q) => $q->whereIn('status', ['upcoming', 'running']))
            ->with('courses:id,title')->orderBy('name')->get(['id', 'name'])
            ->map(fn (Batch $b) => ['id' => $b->id, 'name' => $b->name, 'courses' => $b->courses->map->only('id', 'title')->values()]);
        $allCourseIds = $batches->pluck('courses')->flatten(1)->pluck('id')->unique();

        return Inertia::render('Qa/Index', [
            'questions' => $questions,
            'filters' => $request->only('batch_id', 'filter', 'search'),
            'batches' => $batches,
            'lessons' => \App\Models\Lesson::whereIn('course_id', $allCourseIds)->orderBy('position')->get(['id', 'course_id', 'title']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'course_id' => ['nullable', 'integer', Rule::exists('batch_course', 'course_id')->where('batch_id', $request->integer('batch_id'))],
            'lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('course_id', $request->integer('course_id'))],
            'title' => ['required', 'string', 'min:8', 'max:200'],
            'body' => ['nullable', 'string', 'max:10000'],
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'max:5120'],
        ], ['title.min' => 'Write your question in a few more words.', 'images.max' => 'Up to 3 screenshots.', 'images.*.max' => 'Each screenshot must be under 5 MB.', 'course_id.exists' => 'This course is not part of the chosen batch.']);
        $ids = $user->boardBatchIds();
        abort_unless($ids === null || in_array((int) $data['batch_id'], $ids, true), 403);
        if (empty($data['course_id'])) {
            $data['lesson_id'] = null;
        }

        $q = QaQuestion::create([
            ...collect($data)->except('images')->all(),
            'user_id' => $user->id,
            'images' => $this->storeImages($request),
            'last_activity_at' => now(),
        ]);

        // Tell the batch mentor and the course mentors (bell + email if staff email is on).
        $batch = $q->batch;
        $mentors = collect($batch?->mentor_id ? [User::find($batch->mentor_id)] : [])
            ->merge($q->course ? $q->course->mentors()->get() : collect())
            ->filter()->unique('id')->reject(fn ($m) => $m->id === $user->id);
        if ($mentors->isNotEmpty()) {
            Notification::send($mentors, new QaActivity($q, 'asked', $user->name));
        }

        return redirect()->route('qa.show', $q)->with('success', 'Question posted. Everyone in your batch can answer now.');
    }

    public function show(Request $request, QaQuestion $question): Response
    {
        $user = $request->user();
        $this->guard($user, $question);
        $question->load(['batch:id,name', 'course:id,title', 'lesson:id,title', 'user:id,name', 'answers.user:id,name']);
        $canModerate = $this->canModerate($user, $question);

        return Inertia::render('Qa/Show', [
            'question' => [
                ...$question->only('id', 'title', 'body', 'status', 'is_pinned', 'answers_count'),
                'batch' => $question->batch?->name,
                'course' => $question->course?->title,
                'lesson' => $question->lesson?->title,
                'author' => $question->user->name,
                'is_mine' => $question->user_id === $user->id,
                'images' => collect($question->images ?? [])->keys()->map(fn ($i) => route('qa.image', [$question, $i])),
                'ago' => $question->created_at->diffForHumans(),
            ],
            'answers' => $question->answers->map(fn (QaAnswer $a) => [
                ...$a->only('id', 'body', 'is_staff', 'is_accepted'),
                'author' => $a->user->name,
                'is_mine' => $a->user_id === $user->id,
                'images' => collect($a->images ?? [])->keys()->map(fn ($i) => route('qa.answer-image', [$a, $i])),
                'ago' => $a->created_at->diffForHumans(),
            ]),
            'can' => [
                'moderate' => $canModerate,
                'accept' => $canModerate || $question->user_id === $user->id,
                'answer' => $question->status !== 'closed',
                'ai' => $canModerate && $question->course_id !== null,
            ],
        ]);
    }

    public function answer(Request $request, QaQuestion $question): RedirectResponse
    {
        $user = $request->user();
        $this->guard($user, $question);
        if ($question->status === 'closed') {
            return back()->with('error', 'This question is closed.');
        }
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'max:5120'],
        ]);
        $isStaff = $this->canModerate($user, $question);

        DB::transaction(function () use ($question, $user, $data, $isStaff, $request) {
            $question->answers()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
                'images' => $this->storeImages($request),
                'is_staff' => $isStaff,
            ]);
            $question->update([
                'answers_count' => $question->answers()->count(),
                'last_activity_at' => now(),
                // A mentor's answer marks the question answered.
                'status' => $isStaff && $question->status === 'open' ? 'answered' : $question->status,
            ]);
        });

        if ($question->user_id !== $user->id) {
            $question->user->notify(new QaActivity($question, 'answered', $user->name));
        }

        return back()->with('success', 'Answer posted.');
    }

    /** The asker or a mentor marks the answer that solved it. */
    public function accept(Request $request, QaAnswer $answer): RedirectResponse
    {
        $user = $request->user();
        $question = $answer->question;
        abort_unless($question->user_id === $user->id || $this->canModerate($user, $question), 403);

        DB::transaction(function () use ($question, $answer) {
            $question->answers()->update(['is_accepted' => false]);
            $answer->update(['is_accepted' => true]);
            $question->update(['accepted_answer_id' => $answer->id, 'status' => 'answered']);
        });

        return back()->with('success', 'Marked as the best answer.');
    }

    /** Mentor tools: pin to top, close, reopen. */
    public function moderate(Request $request, QaQuestion $question): RedirectResponse
    {
        abort_unless($this->canModerate($request->user(), $question), 403);
        $action = $request->validate(['action' => ['required', Rule::in(['pin', 'unpin', 'close', 'reopen'])]])['action'];

        $question->update(match ($action) {
            'pin' => ['is_pinned' => true],
            'unpin' => ['is_pinned' => false],
            'close' => ['status' => 'closed'],
            'reopen' => ['status' => $question->answers_count ? 'answered' : 'open'],
        });

        return back()->with('success', 'Done.');
    }

    public function destroy(Request $request, QaQuestion $question): RedirectResponse
    {
        $user = $request->user();
        $mine = $question->user_id === $user->id && $question->answers_count === 0;
        abort_unless($mine || $this->canModerate($user, $question), 403);

        $this->deleteImages($question->images);
        $question->answers()->get()->each(fn ($a) => $this->deleteImages($a->images));
        $question->delete();

        return redirect()->route('qa.index')->with('success', 'Question deleted.');
    }

    public function destroyAnswer(Request $request, QaAnswer $answer): RedirectResponse
    {
        $user = $request->user();
        $question = $answer->question;
        abort_unless($answer->user_id === $user->id || $this->canModerate($user, $question), 403);

        $this->deleteImages($answer->images);
        $answer->delete();
        $question->update([
            'answers_count' => $question->answers()->count(),
            'accepted_answer_id' => $question->accepted_answer_id === $answer->id ? null : $question->accepted_answer_id,
        ]);

        return back()->with('success', 'Answer deleted.');
    }

    /** AI draft answer for mentors — they edit it before posting. */
    public function aiDraft(Request $request, QaQuestion $question, EngagementAi $ai): JsonResponse
    {
        abort_unless($question->course_id && $this->canModerate($request->user(), $question), 403);
        $language = $request->validate(['language' => ['required', Rule::in(['english', 'roman_urdu', 'urdu'])]])['language'];

        try {
            return response()->json(['answer' => $ai->answerDraft($question->load('course', 'lesson'), $language, $request->user())]);
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function image(Request $request, QaQuestion $question, int $index): StreamedResponse
    {
        $this->guard($request->user(), $question);

        return $this->streamImage($question->images ?? [], $index);
    }

    public function answerImage(Request $request, QaAnswer $answer, int $index): StreamedResponse
    {
        $this->guard($request->user(), $answer->question);

        return $this->streamImage($answer->images ?? [], $index);
    }

    private function streamImage(array $images, int $index): StreamedResponse
    {
        $path = $images[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function storeImages(Request $request): ?array
    {
        if (! $request->hasFile('images')) {
            return null;
        }

        return collect($request->file('images'))->take(3)->map(fn ($f) => $f->store('qa/'.now()->format('Y/m'), 'local'))->values()->all();
    }

    private function deleteImages(?array $paths): void
    {
        foreach ($paths ?? [] as $p) {
            Storage::disk('local')->delete($p);
        }
    }
}
