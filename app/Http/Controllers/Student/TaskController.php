<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tasks = Task::visibleTo($user)->with('course:id,title')
            ->with(['submissions' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByRaw('due_at is null')->orderBy('due_at')->latest('id')
            ->get()
            ->map(fn (Task $t) => $this->card($t, $t->submissions->first()));

        return Inertia::render('Student/Tasks/Index', [
            'todo' => $tasks->filter(fn ($t) => in_array($t['state'], ['todo', 'resubmit', 'late'], true))->values(),
            'done' => $tasks->reject(fn ($t) => in_array($t['state'], ['todo', 'resubmit', 'late'], true))->values(),
        ]);
    }

    private function card(Task $t, ?TaskSubmission $s): array
    {
        $state = match (true) {
            $s?->status === 'resubmit' => 'resubmit',
            $s?->status === 'graded' => 'graded',
            $s !== null => 'submitted',
            $t->isOverdue() && ! $t->allow_late => 'missed',
            $t->isOverdue() => 'late',
            default => 'todo',
        };

        return [
            'id' => $t->id,
            'title' => $t->title,
            'course' => $t->course->title,
            'max_marks' => $t->max_marks,
            'marks' => $s?->status === 'graded' ? $s->marks : null,
            'state' => $state,
            'due_label' => $t->due_at?->setTimezone(config('lwe.timezone'))->format('D d M, g:i A'),
            'due_iso' => $t->due_at?->toIso8601String(),
        ];
    }

    public function show(Request $request, Task $task): Response
    {
        $user = $request->user();
        abort_unless($task->isVisibleTo($user), 404);
        $task->load('course:id,title,slug', 'lesson:id,title');
        $s = $task->submissions()->where('user_id', $user->id)->first();

        return Inertia::render('Student/Tasks/Show', [
            'task' => [
                ...$task->only('id', 'title', 'instructions', 'max_marks', 'submission_type', 'attachment_name', 'allow_late'),
                'course' => $task->course->title,
                'lesson' => $task->lesson?->title,
                'due_label' => $task->due_at?->setTimezone(config('lwe.timezone'))->format('D d M Y, g:i A'),
                'overdue' => $task->isOverdue(),
                'can_submit' => $task->acceptsSubmissions() && $s?->status !== 'graded',
            ],
            'submission' => $s ? [
                ...$s->only('body', 'link', 'file_name', 'status', 'marks', 'feedback', 'is_late'),
                'submitted_label' => $s->submitted_at->setTimezone(config('lwe.timezone'))->format('D d M, g:i A'),
            ] : null,
        ]);
    }

    public function submit(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless($task->isVisibleTo($user), 404);
        if (! $task->acceptsSubmissions()) {
            return back()->with('error', 'This task is closed. The last date has passed.');
        }
        $existing = $task->submissions()->where('user_id', $user->id)->first();
        if ($existing?->status === 'graded') {
            return back()->with('error', 'This task is already checked. Ask your mentor if you want to submit again.');
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'file' => ['nullable', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,ppt,pptx,xls,xlsx,zip,txt,mp4'],
        ], ['link.url' => 'Paste a full link starting with https://', 'file.max' => 'The file must be under 15 MB.']);

        $hasFile = $request->hasFile('file') || $existing?->file_path;
        $need = match ($task->submission_type) {
            'text' => filled($data['body'] ?? null),
            'link' => filled($data['link'] ?? null),
            'file' => $hasFile,
            default => filled($data['body'] ?? null) || filled($data['link'] ?? null) || $hasFile,
        };
        if (! $need) {
            throw ValidationException::withMessages(['body' => match ($task->submission_type) {
                'text' => 'Write your answer.',
                'link' => 'Paste the link to your work.',
                'file' => 'Upload your file or screenshot.',
                default => 'Add your answer, a link or a file.',
            }]);
        }

        $sub = $existing ?? new TaskSubmission(['task_id' => $task->id, 'user_id' => $user->id]);
        if ($request->hasFile('file')) {
            if ($sub->file_path) {
                Storage::disk('local')->delete($sub->file_path);
            }
            $sub->file_path = $request->file('file')->store('submissions/'.$task->id, 'local');
            $sub->file_name = mb_substr($request->file('file')->getClientOriginalName(), 0, 200);
        }
        $sub->fill([
            'body' => $data['body'] ?? null,
            'link' => $data['link'] ?? null,
            'submitted_at' => now(),
            'is_late' => $task->isOverdue(),
            'status' => 'submitted',
            // A fresh submission needs a fresh check.
            'ai_marks' => null,
            'ai_feedback' => null,
        ])->save();

        // Mentors of the course see it on their bell (no email for every submission).
        $mentors = $task->course->mentors()->get();
        if ($mentors->isNotEmpty()) {
            Notification::send($mentors, new \App\Notifications\SimpleAlert(
                'Task submitted: '.$task->title, "{$user->name} ({$user->student_code})", route('manage.tasks.show', $task, false), 'tasks'));
        }

        return back()->with('success', $existing ? 'Your work is updated.' : 'Submitted! Your mentor will check it.');
    }

    /** The task's attachment — for its students and its staff. */
    public function attachment(Request $request, Task $task): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->canManageCourse($task->course_id) || $task->isVisibleTo($user), 403);
        abort_unless($task->attachment_path && Storage::disk('local')->exists($task->attachment_path), 404);

        return Storage::disk('local')->download($task->attachment_path, $task->attachment_name);
    }

    public function myFile(Request $request, Task $task): StreamedResponse
    {
        $s = $task->submissions()->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($s->file_path && Storage::disk('local')->exists($s->file_path), 404);

        return Storage::disk('local')->response($s->file_path, $s->file_name);
    }
}
