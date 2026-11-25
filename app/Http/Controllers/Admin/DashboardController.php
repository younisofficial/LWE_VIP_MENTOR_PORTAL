<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(\App\Services\ReportService $reports): Response
    {
        [$from, $to] = $reports->range('month');
        $tz = config('lwe.timezone');
        $todayStart = now($tz)->startOfDay()->utc();
        $todayEnd = now($tz)->endOfDay()->utc();

        return Inertia::render('Admin/Dashboard', [
            'kpis' => $reports->kpis($from, $to),
            'revenueByMonth' => $reports->revenueByMonth(),
            'attendanceByWeek' => $reports->attendanceByWeek(),
            'today' => [
                'classes' => \App\Models\ClassSession::whereBetween('starts_at', [$todayStart, $todayEnd])->where('status', '!=', 'cancelled')->count(),
                'checkins' => \App\Models\DailyCheckin::whereDate('date', now($tz)->toDateString())->count(),
                'to_check' => \App\Models\TaskSubmission::where('status', 'submitted')->count(),
                'unanswered' => \App\Models\QaQuestion::where('status', 'open')->where('answers_count', 0)->count(),
            ],
            'stats' => [
                'students' => User::role(User::ROLE_STUDENT)->count(),
                'active_enrollments' => Enrollment::where('status', 'active')->count(),
                'pending_payments' => Payment::where('status', 'pending')->count(),
                'revenue_month' => (float) Payment::where('status', 'approved')
                    ->where('reviewed_at', '>=', now()->startOfMonth())->sum('amount'),
                'published_courses' => Course::published()->count(),
                'running_batches' => Batch::whereIn('status', ['upcoming', 'running'])->count(),
                'flagged_students' => User::whereNotNull('flagged_at')->where('flagged_at', '>=', now()->subDays(7))->count(),
            ],
            'recentPayments' => Payment::with(['user:id,name,student_code', 'enrollment.course:id,title'])
                ->where('status', 'pending')->latest()->limit(6)->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'student' => $p->user->name,
                    'code' => $p->user->student_code,
                    'course' => $p->enrollment->course->title,
                    'amount' => (float) $p->amount,
                    'time' => $p->created_at->diffForHumans(),
                ]),
            'recentEnrollments' => Enrollment::with(['user:id,name', 'course:id,title'])
                ->latest()->limit(6)->get()
                ->map(fn (Enrollment $e) => [
                    'id' => $e->id,
                    'student' => $e->user->name,
                    'course' => $e->course->title,
                    'status' => $e->status,
                    'source' => $e->source,
                    'time' => $e->created_at->diffForHumans(),
                ]),
        ]);
    }
}
