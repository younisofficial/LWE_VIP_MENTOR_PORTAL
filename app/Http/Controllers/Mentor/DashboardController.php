<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Module 1 mentor view: my courses and batches. Classes, tasks and grading come in Modules 2–3. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $tz = config('lwe.timezone');
        $sessions = \App\Models\ClassSession::with(['course:id,title', 'batch:id,name'])
            ->where('mentor_id', $user->id)->whereIn('status', ['scheduled', 'live'])
            ->where('starts_at', '>=', now()->subHours(4))->orderBy('starts_at')->limit(20)->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'course' => $s->course->title,
                'batch' => $s->batch->name,
                'status' => $s->status,
                'label' => $s->starts_at->setTimezone($tz)->format('D d M, g:i A'),
                'can_start' => $s->zoom_meeting_id && now()->gte($s->starts_at->subMinutes(30)),
                'zoom_ready' => (bool) $s->zoom_meeting_id,
            ]);

        // Module 5: what is waiting for this mentor
        $courseIds = $user->mentoredCourses()->pluck('courses.id');
        $todo = [
            'to_check' => \App\Models\TaskSubmission::where('status', 'submitted')->whereHas('task', fn ($t) => $t->whereIn('course_id', $courseIds))->count(),
            'unanswered' => \App\Models\QaQuestion::where(fn ($q) => $q->whereIn('batch_id', $user->boardBatchIds() ?? [])->orWhereIn('course_id', $courseIds))->where('status', 'open')->where('answers_count', 0)->count(),
            'at_risk' => \App\Models\Enrollment::with(['user:id,name,student_code', 'course:id,title'])->whereIn('course_id', $courseIds)
                ->where('status', 'active')->where('progress_status', 'at_risk')->orderBy('score')->limit(8)->get()
                ->map(fn ($e) => ['name' => $e->user->name, 'code' => $e->user->student_code, 'course' => $e->course->title, 'notes' => $e->progress_notes ?? []]),
        ];

        return Inertia::render('Mentor/Dashboard', [
            'todo' => $todo,
            'sessions' => $sessions,
            'courses' => $user->mentoredCourses()->withCount(['lessons', 'enrollments'])->get(['courses.id', 'title', 'slug', 'status']),
            'batches' => Batch::with('courses:id,title')
                ->where('mentor_id', $user->id)
                ->withCount(['enrollments as students_count' => fn ($q) => $q->where('status', 'active')])
                ->orderBy('starts_on')
                ->get()
                ->map(fn (Batch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'course' => $b->courses->pluck('title')->implode(', '),
                    'status' => $b->status,
                    'students' => $b->students_count,
                    'next_class' => $b->nextClassAt()?->format('D d M, g:i A'),
                ]),
        ]);
    }
}
