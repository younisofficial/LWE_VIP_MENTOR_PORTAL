<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Jobs\ReviewSubmissionWithAi;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Notifications\TaskGraded;
use App\Notifications\TaskPublished;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\EngagementAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Tasks (assignments) for admins and mentors: create, publish, check submissions. */
class TaskController extends Controller
{
    use ManagesCourses;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tasks = $this->scopeToUser(Task::query(), $user)
            ->with(['course:id,title', 'batch:id,name', 'lesson:id,title'])
            ->withCount([
                'submissions',
                'submissions as to_check_count' => fn ($q) => $q->where('status', 'submitted'),
            ])
            ->when($request->course_id, fn ($q, $id) => $q->where('course_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Task $t) => [
                ...$t->only('id', 'course_id', 'lesson_id', 'batch_id', 'title', 'instructions', 'rubric', 'submission_type', 'max_marks', 'allow_late', 'status', 'attachment_name'),
                'course' => $t->course->title,
                'batch' => $t->batch?->name,
                'lesson' => $t->lesson?->title,
                'due_at' => $t->due_at?->setTimezone(config('lwe.timezone'))->format('Y-m-d\TH:i'),
                'due_label' => $t->due_at?->setTimezone(config('lwe.timezone'))->format('d M, g:i A'),
                'submissions_count' => $t->submissions_count,
                'to_check_count' => $t->to_check_count,
                'expected' => $t->audience()->count(),
            ]);

        return Inertia::render('Manage/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('course_id', 'status'),
            ...$this->courseOptions($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeCourse($request->user(), $data['course_id']);

        $task = new Task([...$data, 'created_by' => $request->user()->id]);
        $this->saveAttachment($request, $task);
        $task->save();
        $this->announceIfPublished($task, false);

        return back()->with('success', $task->status === 'published' ? 'Task published. Students were told.' : 'Task saved as draft.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $task->course_id);
        $data = $this->validated($request);
        $this->authorizeCourse($request->user(), $data['course_id']);

        $wasPublished = $task->status === 'published';
        $task->fill($data);
        $this->saveAttachment($request, $task);
        $task->save();
        $this->announceIfPublished($task, $wasPublished);

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $task->course_id);
        if ($task->submissions()->exists()) {
            return back()->with('error', 'Students have submitted this task. Change it to Draft instead of deleting.');
        }
        if ($task->attachment_path) {
            Storage::disk('local')->delete($task->attachment_path);
        }
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }

    /** All students of the task, with their submission (or "not submitted"). */
    public function show(Request $request, Task $task): Response
    {
        $this->authorizeCourse($request->user(), $task->course_id);
        $task->load('course:id,title', 'batch:id,name');
        $subs = $task->submissions()->get()->keyBy('user_id');

        $rows = $task->audience()->orderBy('name')->get(['id', 'name', 'student_code', 'phone'])
            ->map(fn ($u) => ['student' => $u->only('id', 'name', 'student_code', 'phone'), 'submission' => $this->submissionRow($subs->get($u->id))]);
        // People who submitted but are no longer in the audience (e.g. moved batch) still show.
        $missing = $subs->keys()->diff($rows->pluck('student.id'));
        if ($missing->isNotEmpty()) {
            \App\Models\User::whereIn('id', $missing)->get(['id', 'name', 'student_code', 'phone'])
                ->each(fn ($u) => $rows->push(['student' => $u->only('id', 'name', 'student_code', 'phone'), 'submission' => $this->submissionRow($subs->get($u->id))]));
        }

        return Inertia::render('Manage/Tasks/Show', [
            'task' => [
                ...$task->only('id', 'title', 'instructions', 'rubric', 'max_marks', 'status', 'attachment_name', 'submission_type'),
                'course' => $task->course->title,
                'batch' => $task->batch?->name,
                'due_label' => $task->due_at?->setTimezone(config('lwe.timezone'))->format('D d M Y, g:i A'),
            ],
            'rows' => $rows->values(),
            'stats' => [
                'expected' => $rows->count(),
                'submitted' => $subs->count(),
                'to_check' => $subs->where('status', 'submitted')->count(),
                'average' => $subs->whereNotNull('marks')->avg('marks'),
            ],
        ]);
    }

    private function submissionRow(?TaskSubmission $s): ?array
    {
        if (! $s) {
            return null;
        }

        return [
            ...$s->only('id', 'body', 'link', 'file_name', 'status', 'marks', 'feedback', 'ai_marks', 'ai_feedback', 'is_late'),
            'submitted_label' => $s->submitted_at->setTimezone(config('lwe.timezone'))->format('d M, g:i A'),
            'file_is_image' => $s->file_name && preg_match('/\.(jpe?g|png|gif|webp)$/i', $s->file_name) === 1,
        ];
    }

    public function grade(Request $request, TaskSubmission $submission): RedirectResponse
    {
        $task = $submission->task;
        $this->authorizeCourse($request->user(), $task->course_id);
        $data = $request->validate([
            'marks' => ['nullable', 'numeric', 'min:0', 'max:'.$task->max_marks, 'required_unless:status,resubmit'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['graded', 'resubmit'])],
        ]);

        $submission->update([
            'marks' => $data['status'] === 'graded' ? $data['marks'] : null,
            'feedback' => $data['feedback'] ?? null,
            'status' => $data['status'],
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
        ]);
        $submission->user->notify(new TaskGraded($submission->load('task')));

        return back()->with('success', $data['status'] === 'graded' ? 'Marks saved. The student was told.' : 'Sent back to the student to improve.');
    }

    /** AI suggestion for one submission (shown to the mentor, not the student). */
    public function aiReview(Request $request, TaskSubmission $submission, EngagementAi $ai): JsonResponse
    {
        $this->authorizeCourse($request->user(), $submission->task->course_id);
        $language = $request->validate(['language' => ['nullable', Rule::in(['english', 'roman_urdu', 'urdu'])]])['language'] ?? 'roman_urdu';

        try {
            $result = $ai->reviewSubmission($submission->load('task'), $language, $request->user());
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $submission->update(['ai_marks' => $result['marks'], 'ai_feedback' => $result['feedback']]);

        return response()->json($result);
    }

    /** Queue AI suggestions for every unchecked submission of a task. */
    public function aiReviewAll(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $task->course_id);
        $language = $request->input('language', 'roman_urdu');
        $pending = $task->submissions()->where('status', 'submitted')->whereNull('ai_feedback')->limit(100)->get();
        $pending->each(fn ($s) => ReviewSubmissionWithAi::dispatch($s, $request->user(), in_array($language, ['english', 'roman_urdu', 'urdu'], true) ? $language : 'roman_urdu'));

        return back()->with('success', $pending->isEmpty()
            ? 'Nothing to check: every submission already has an AI suggestion or marks.'
            : "AI is checking {$pending->count()} submission(s). Refresh in a minute to see the suggestions.");
    }

    /** AI writes a task draft for the form. */
    public function aiDraft(Request $request, EngagementAi $ai): JsonResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'topic' => ['nullable', 'string', 'max:2000'],
            'language' => ['required', Rule::in(['english', 'roman_urdu', 'urdu'])],
        ]);
        $this->authorizeCourse($request->user(), (int) $data['course_id']);
        if (empty($data['lesson_id']) && blank($data['topic'] ?? null)) {
            return response()->json(['message' => 'Choose a lesson or write a topic first.'], 422);
        }

        try {
            return response()->json($ai->taskDraft(Course::findOrFail($data['course_id']),
                ! empty($data['lesson_id']) ? Lesson::find($data['lesson_id']) : null, $data['topic'] ?? null, $data['language'], $request->user()));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function submissionFile(Request $request, TaskSubmission $submission): StreamedResponse
    {
        $this->authorizeCourse($request->user(), $submission->task->course_id);
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->response($submission->file_path, $submission->file_name);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('course_id', $request->integer('course_id'))],
            'batch_id' => ['nullable', 'integer', Rule::exists('batch_course', 'batch_id')->where('course_id', $request->integer('course_id'))],
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['required', 'string', 'max:20000'],
            'rubric' => ['nullable', 'string', 'max:5000'],
            'submission_type' => ['required', Rule::in(Task::SUBMISSION_TYPES)],
            'max_marks' => ['required', 'integer', 'between:1,100'],
            'due_at' => ['nullable', 'date'],
            'allow_late' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,jpg,jpeg,png,txt'],
        ], ['lesson_id.exists' => 'That lesson is not in this course.', 'batch_id.exists' => 'That batch does not teach this course.']);

        // The form sends Pakistan time.
        $data['due_at'] = ! empty($data['due_at'])
            ? \Carbon\Carbon::parse($data['due_at'], config('lwe.timezone'))->utc()
            : null;
        unset($data['attachment']);

        return $data;
    }

    private function saveAttachment(Request $request, Task $task): void
    {
        if (! $request->hasFile('attachment')) {
            return;
        }
        if ($task->attachment_path) {
            Storage::disk('local')->delete($task->attachment_path);
        }
        $file = $request->file('attachment');
        $task->attachment_path = $file->store('tasks', 'local');
        $task->attachment_name = mb_substr($file->getClientOriginalName(), 0, 200);
    }

    private function announceIfPublished(Task $task, bool $wasPublished): void
    {
        if ($task->status !== 'published' || $wasPublished) {
            return;
        }
        $task->forceFill(['published_at' => now()])->saveQuietly();
        Notification::send($task->audience()->get(), new TaskPublished($task->load('course:id,title')));
    }
}
