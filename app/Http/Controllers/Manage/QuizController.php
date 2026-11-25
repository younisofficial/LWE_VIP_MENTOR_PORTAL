<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Notifications\QuizPublished;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\EngagementAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Quizzes for admins and mentors: builder (with AI questions), publish, results. */
class QuizController extends Controller
{
    use ManagesCourses;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $quizzes = $this->scopeToUser(Quiz::query(), $user)
            ->with(['course:id,title', 'batch:id,name'])
            ->withCount(['questions', 'attempts as attempts_count' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->when($request->course_id, fn ($q, $id) => $q->where('course_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Quiz $q) => [
                ...$q->only('id', 'title', 'status', 'time_limit_minutes', 'pass_percent', 'max_attempts'),
                'course' => $q->course->title,
                'batch' => $q->batch?->name,
                'questions_count' => $q->questions_count,
                'attempts_count' => $q->attempts_count,
                'average' => round((float) $q->attempts()->whereNotNull('submitted_at')->avg('percent'), 1),
                'due_label' => $q->due_at?->setTimezone(config('lwe.timezone'))->format('d M, g:i A'),
            ]);

        return Inertia::render('Manage/Quizzes/Index', [
            'quizzes' => $quizzes,
            'filters' => $request->only('course_id'),
            ...$this->courseOptions($user),
        ]);
    }

    /** Step 1: course + title. Then the builder opens. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('course_id', $request->integer('course_id'))],
            'title' => ['required', 'string', 'max:200'],
        ]);
        $this->authorizeCourse($request->user(), $data['course_id']);
        $quiz = Quiz::create([...$data, 'created_by' => $request->user()->id]);

        return redirect()->route('manage.quizzes.edit', $quiz)->with('success', 'Quiz created. Add questions — or let AI write them.');
    }

    public function edit(Request $request, Quiz $quiz): Response
    {
        $this->authorizeCourse($request->user(), $quiz->course_id);
        $quiz->load('course:id,title');

        return Inertia::render('Manage/Quizzes/Edit', [
            'quiz' => [
                ...$quiz->only('id', 'course_id', 'lesson_id', 'batch_id', 'title', 'instructions', 'time_limit_minutes',
                    'pass_percent', 'max_attempts', 'shuffle_questions', 'show_answers', 'status'),
                'course' => $quiz->course->title,
                'due_at' => $quiz->due_at?->setTimezone(config('lwe.timezone'))->format('Y-m-d\TH:i'),
                'has_attempts' => $quiz->attempts()->exists(),
            ],
            'questions' => $quiz->questions()->get(['id', 'type', 'question', 'options', 'correct', 'explanation', 'marks']),
            'lessons' => Lesson::where('course_id', $quiz->course_id)->orderBy('position')->get(['id', 'title']),
            'batches' => \App\Models\Batch::whereHas('courses', fn ($q) => $q->whereKey($quiz->course_id))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Saves settings and the full question list in one go. */
    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $quiz->course_id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('course_id', $quiz->course_id)],
            'batch_id' => ['nullable', 'integer', Rule::exists('batch_course', 'batch_id')->where('course_id', $quiz->course_id)],
            'time_limit_minutes' => ['nullable', 'integer', 'between:1,300'],
            'pass_percent' => ['required', 'integer', 'between:0,100'],
            'max_attempts' => ['required', 'integer', 'between:1,10'],
            'shuffle_questions' => ['boolean'],
            'show_answers' => ['boolean'],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'questions' => ['array', 'max:100'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.type' => ['required', Rule::in(['single', 'multiple', 'true_false'])],
            'questions.*.question' => ['required', 'string', 'max:2000'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:8'],
            'questions.*.options.*' => ['required', 'string', 'max:500'],
            'questions.*.correct' => ['required', 'array', 'min:1'],
            'questions.*.correct.*' => ['integer', 'min:0'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1000'],
            'questions.*.marks' => ['required', 'integer', 'between:1,10'],
        ], [
            'questions.*.correct.required' => 'Tick the right answer for every question.',
            'questions.*.options.*.required' => 'An option is empty.',
            'questions.*.question.required' => 'A question is empty.',
        ]);

        $questions = collect($data['questions'] ?? []);
        foreach ($questions as $i => $q) {
            if (collect($q['correct'])->contains(fn ($c) => $c >= count($q['options']))) {
                throw ValidationException::withMessages(["questions.{$i}.correct" => 'Question '.($i + 1).': the right answer is not one of the options.']);
            }
            if ($q['type'] !== 'multiple' && count($q['correct']) > 1) {
                throw ValidationException::withMessages(["questions.{$i}.correct" => 'Question '.($i + 1).': only one right answer is allowed (or change the type to "multiple").']);
            }
        }
        if ($data['status'] === 'published' && $questions->isEmpty()) {
            throw ValidationException::withMessages(['status' => 'Add at least one question before publishing.']);
        }

        $wasPublished = $quiz->status === 'published';
        DB::transaction(function () use ($quiz, $data, $questions) {
            $quiz->update([
                ...collect($data)->except('questions', 'due_at')->all(),
                'due_at' => ! empty($data['due_at']) ? \Carbon\Carbon::parse($data['due_at'], config('lwe.timezone'))->utc() : null,
            ]);

            // Keep ids of questions that stay (old attempts point to them), delete removed ones.
            $keep = [];
            foreach ($questions->values() as $pos => $q) {
                $attrs = [
                    'type' => $q['type'],
                    'question' => $q['question'],
                    'options' => $q['type'] === 'true_false' ? ['True', 'False'] : array_values($q['options']),
                    'correct' => array_values(array_unique(array_map('intval', $q['correct']))),
                    'explanation' => $q['explanation'] ?? null,
                    'marks' => $q['marks'],
                    'position' => $pos,
                ];
                $existing = ! empty($q['id']) ? $quiz->questions()->whereKey($q['id'])->first() : null;
                $keep[] = $existing ? tap($existing)->update($attrs)->id : $quiz->questions()->create($attrs)->id;
            }
            $quiz->questions()->whereNotIn('id', $keep)->delete();
        });

        if ($quiz->status === 'published' && ! $wasPublished) {
            $quiz->forceFill(['published_at' => now()])->saveQuietly();
            Notification::send($quiz->audience()->get(), new QuizPublished($quiz->load('course:id,title')));

            return back()->with('success', 'Quiz published. Students were told.');
        }

        return back()->with('success', 'Quiz saved.');
    }

    public function destroy(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $quiz->course_id);
        if ($quiz->attempts()->exists()) {
            return back()->with('error', 'Students have taken this quiz. Change it to Draft instead of deleting.');
        }
        $quiz->delete();

        return redirect()->route('manage.quizzes.index')->with('success', 'Quiz deleted.');
    }

    /** AI writes questions; the builder adds them for the mentor to check. Nothing is saved here. */
    public function ai(Request $request, Quiz $quiz, EngagementAi $ai): JsonResponse
    {
        $this->authorizeCourse($request->user(), $quiz->course_id);
        $data = $request->validate([
            'lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('course_id', $quiz->course_id)],
            'topic' => ['nullable', 'string', 'max:3000'],
            'count' => ['required', 'integer', 'between:1,25'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'language' => ['required', Rule::in(['english', 'roman_urdu', 'urdu'])],
        ]);
        if (empty($data['lesson_id']) && blank($data['topic'] ?? null)) {
            return response()->json(['message' => 'Choose a lesson or write a topic first.'], 422);
        }

        try {
            $questions = $ai->quiz(Course::findOrFail($quiz->course_id), ! empty($data['lesson_id']) ? Lesson::find($data['lesson_id']) : null,
                $data['topic'] ?? null, $data['count'], $data['difficulty'], $data['language'], $request->user());
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['questions' => $questions]);
    }

    public function results(Request $request, Quiz $quiz): Response
    {
        $this->authorizeCourse($request->user(), $quiz->course_id);
        $quiz->load('course:id,title');
        $attempts = $quiz->attempts()->whereNotNull('submitted_at')->with('user:id,name,student_code')->get()->groupBy('user_id');

        $rows = $quiz->audience()->orderBy('name')->get(['id', 'name', 'student_code'])->map(function ($u) use ($attempts) {
            $mine = $attempts->get($u->id, collect());
            $best = $mine->sortByDesc('percent')->first();

            return [
                'student' => $u->only('id', 'name', 'student_code'),
                'attempts' => $mine->count(),
                'best_percent' => $best?->percent,
                'best_score' => $best ? "{$best->score}/{$best->max_score}" : null,
                'passed' => (bool) $best?->passed,
                'last_label' => $mine->max('submitted_at')?->setTimezone(config('lwe.timezone'))->format('d M, g:i A'),
            ];
        });

        // Hardest questions: lowest share of right answers.
        $questions = $quiz->questions()->get();
        $all = $attempts->flatten();
        $hardest = $questions->map(function ($q) use ($all) {
            $answered = $all->filter(fn ($a) => isset(($a->answers ?? [])[$q->id]));
            $right = $answered->filter(fn ($a) => $q->isCorrect((array) $a->answers[$q->id]))->count();

            return ['question' => $q->question, 'answered' => $answered->count(), 'right_percent' => $answered->count() ? round($right * 100 / $answered->count()) : null];
        })->filter(fn ($x) => $x['answered'] > 0)->sortBy('right_percent')->take(5)->values();

        return Inertia::render('Manage/Quizzes/Results', [
            'quiz' => [...$quiz->only('id', 'title', 'pass_percent', 'max_attempts'), 'course' => $quiz->course->title],
            'rows' => $rows,
            'hardest' => $hardest,
            'stats' => [
                'took' => $attempts->count(),
                'expected' => $rows->count(),
                'average' => $all->count() ? round($all->avg('percent'), 1) : null,
                'passed' => $rows->where('passed', true)->count(),
            ],
        ]);
    }

    /** Gives a student their attempts back (e.g. internet dropped). */
    public function resetAttempts(Request $request, Quiz $quiz, int $userId): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $quiz->course_id);
        $quiz->attempts()->where('user_id', $userId)->delete();

        return back()->with('success', 'Attempts cleared. The student can take the quiz again.');
    }
}
