<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The student's home screen: next class, my courses with progress, payment status. */
class TodayController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $tz = config('lwe.timezone');

        $enrollments = $user->enrollments()
            ->with(['course:id,title,slug,thumbnail_path,is_vip,fee', 'batch', 'batch.mentor:id,name', 'payments'])
            ->whereIn('status', ['active', 'pending_payment', 'completed'])
            ->latest()
            ->get();

        $completedIds = $user->completedLessons()->pluck('lessons.id')->all();

        $courses = $enrollments->map(function (Enrollment $e) use ($completedIds) {
            $lessons = Lesson::where('lessons.course_id', $e->course_id)
                ->join('course_modules', 'course_modules.id', '=', 'lessons.course_module_id')
                ->orderBy('course_modules.position')->orderBy('lessons.position')
                ->get(['lessons.id', 'lessons.title']);
            $total = $lessons->count();
            $done = $lessons->filter(fn ($l) => in_array($l->id, $completedIds, true))->count();
            $nextTitle = $lessons->first(fn ($l) => ! in_array($l->id, $completedIds, true))?->title;
            $latestPayment = $e->payments->first();

            return [
                'id' => $e->id,
                'status' => $e->status,
                'title' => $e->course->title,
                'slug' => $e->course->slug,
                'thumbnail_url' => $e->course->thumbnail_url,
                'is_vip' => $e->course->is_vip,
                'is_free' => $e->course->is_free,
                'batch' => $e->batch?->name,
                'progress' => $total ? (int) round($done / $total * 100) : 0,
                'next_lesson' => $nextTitle ?: null,
                'amount_due' => (float) $e->amount_due,
                'payment_status' => $latestPayment?->status,
                'payment_note' => $latestPayment?->status === 'rejected' ? $latestPayment->review_note : null,
            ];
        });

        // Module 3: what is waiting today
        $me = $request->user();
        $engage = [
            'tasks_todo' => \App\Models\Task::visibleTo($me)
                ->whereDoesntHave('submissions', fn ($q) => $q->where('user_id', $me->id)->where('status', '!=', 'resubmit'))
                ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>', now())->orWhere('allow_late', true))->count(),
            'quizzes_todo' => \App\Models\Quiz::visibleTo($me)->whereDoesntHave('attempts', fn ($q) => $q->where('user_id', $me->id))
                ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>', now()))->count(),
            'checked_in' => \App\Models\DailyCheckin::where('user_id', $me->id)->whereDate('date', CarbonImmutable::now($tz)->toDateString())->exists(),
        ];

        // Next real live class (Module 2). Falls back to the batch timetable if none is scheduled yet.
        $session = \App\Models\ClassSession::with(['course:id,title', 'batch:id,name', 'mentor:id,name'])
            ->forStudent($request->user()->id)->whereIn('status', ['scheduled', 'live'])
            ->where('starts_at', '>=', now()->subHours(6))->orderBy('starts_at')->get()
            ->first(fn ($s) => $s->status === 'live' || $s->endsAt()->addMinutes(30)->isFuture());

        if ($session) {
            $at = $session->starts_at->setTimezone($tz);

            return Inertia::render('Student/Today', [
                'courses' => $courses,
                'engage' => $engage,
                'nextClass' => [
                    'id' => $session->id,
                    'course' => $session->title,
                    'batch' => $session->course->title.' · '.$session->batch->name,
                    'mentor' => $session->mentor?->name,
                    'starts_at_iso' => $session->starts_at->toIso8601String(),
                    'join_opens_iso' => $session->joinOpensAt()->toIso8601String(),
                    'join_closes_iso' => $session->endsAt()->addMinutes(30)->toIso8601String(),
                    'label' => $at->isToday() ? 'Today' : $at->format('l, d M'),
                    'time' => $at->format('g:i A').' – '.$session->endsAt()->setTimezone($tz)->format('g:i A'),
                    'is_live_now' => $session->status === 'live',
                    'join_available' => (bool) $session->zoom_meeting_id,
                    'join_url' => route('student.classes.join', $session),
                ],
                'stats' => [
                    'active' => $enrollments->where('status', 'active')->count(),
                    'completed' => $enrollments->where('status', 'completed')->count(),
                    'lessons_done' => count($completedIds),
                    'pending_payments' => $enrollments->where('status', 'pending_payment')->count(),
                ],
                'today' => CarbonImmutable::now($tz)->format('l, d F'),
            ]);
        }

        $next = $enrollments->where('status', 'active')
            ->filter(fn ($e) => $e->batch)
            ->map(fn ($e) => ['enrollment' => $e, 'at' => $e->batch->nextClassAt()])
            ->filter(fn ($x) => $x['at'])
            ->sortBy(fn ($x) => $x['at']->timestamp)
            ->first();

        $nextClass = null;
        if ($next) {
            $at = $next['at'];
            $e = $next['enrollment'];
            $now = CarbonImmutable::now($tz);
            $nextClass = [
                'course' => $e->course->title,
                'batch' => $e->batch->name,
                'mentor' => $e->batch->mentor?->name,
                'starts_at_iso' => $at->toIso8601String(),
                'label' => $at->isToday() ? 'Today' : $at->format('l, d M'),
                'time' => $at->format('g:i A').' – '.$at->addMinutes($e->batch->class_duration_minutes)->format('g:i A'),
                'is_live_now' => $now->between($at, $at->addMinutes($e->batch->class_duration_minutes)),
                // The real "Join" button arrives in Module 2 (Zoom inside the portal).
                'join_available' => false,
            ];
        }

        return Inertia::render('Student/Today', [
            'courses' => $courses,
            'engage' => $engage,
            'nextClass' => $nextClass,
            'stats' => [
                'active' => $enrollments->where('status', 'active')->count(),
                'completed' => $enrollments->where('status', 'completed')->count(),
                'lessons_done' => count($completedIds),
                'pending_payments' => $enrollments->where('status', 'pending_payment')->count(),
            ],
            'today' => CarbonImmutable::now($tz)->format('l, d F'),
        ]);
    }
}
