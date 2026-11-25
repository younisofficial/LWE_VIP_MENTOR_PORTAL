<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\DailyCheckin;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\QaAnswer;
use App\Models\QaQuestion;
use App\Models\QuizAttempt;
use App\Models\TaskSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * All numbers for dashboards and reports. Grouping by day/week/month is done in PHP (Pakistan time),
 * so it works the same on MySQL and SQLite.
 */
class ReportService
{
    private string $tz;

    public function __construct()
    {
        $this->tz = config('lwe.timezone');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} from, to (UTC) and a label */
    public function range(?string $preset, ?string $from = null, ?string $to = null): array
    {
        $now = CarbonImmutable::now($this->tz);
        [$a, $b, $label] = match ($preset) {
            'today' => [$now->startOfDay(), $now, 'Today'],
            '7d' => [$now->subDays(6)->startOfDay(), $now, 'Last 7 days'],
            '90d' => [$now->subDays(89)->startOfDay(), $now, 'Last 90 days'],
            'month' => [$now->startOfMonth(), $now, 'This month'],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), 'Last month'],
            'year' => [$now->startOfYear(), $now, 'This year'],
            'custom' => [
                $from ? CarbonImmutable::parse($from, $this->tz)->startOfDay() : $now->subDays(29)->startOfDay(),
                $to ? CarbonImmutable::parse($to, $this->tz)->endOfDay() : $now,
                'Custom',
            ],
            default => [$now->subDays(29)->startOfDay(), $now, 'Last 30 days'],
        };
        if ($b->lt($a)) {
            [$a, $b] = [$b->startOfDay(), $a->endOfDay()];
        }

        return [$a->utc(), $b->utc(), $label];
    }

    private function revenueIn(CarbonImmutable $from, CarbonImmutable $to, ?array $courseIds = null): float
    {
        return (float) Payment::where('status', 'approved')->whereBetween('reviewed_at', [$from, $to])
            ->when($courseIds !== null, fn ($q) => $q->whereHas('enrollment', fn ($e) => $e->whereIn('course_id', $courseIds)))
            ->get(['amount', 'refunded_amount'])->sum(fn ($p) => (float) $p->amount - (float) ($p->refunded_amount ?? 0));
    }

    private function attendanceRate(Collection $statuses): ?float
    {
        if ($statuses->isEmpty()) {
            return null;
        }
        $points = $statuses->sum(fn ($s) => $s === 'present' ? 1 : ($s === 'partial' ? 0.5 : 0));

        return round($points * 100 / $statuses->count(), 1);
    }

    private function sessionIdsIn(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return ClassSession::whereBetween('starts_at', [$from, $to])->where('status', 'ended')->pluck('id');
    }

    private function delta(float|int|null $now, float|int|null $before): ?float
    {
        if ($now === null || $before === null || (float) $before == 0.0) {
            return null;
        }

        return round(($now - $before) * 100 / $before, 1);
    }

    /** Headline numbers for a period, with the change against the period before it. */
    public function kpis(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $len = $from->diffInSeconds($to);
        $pFrom = $from->subSeconds((int) $len + 1);
        $pTo = $from->subSecond();

        $revenue = $this->revenueIn($from, $to);
        $prevRevenue = $this->revenueIn($pFrom, $pTo);
        $enrolls = Enrollment::whereBetween('created_at', [$from, $to])->count();
        $prevEnrolls = Enrollment::whereBetween('created_at', [$pFrom, $pTo])->count();
        $att = $this->attendanceRate(Attendance::whereIn('class_session_id', $this->sessionIdsIn($from, $to))->pluck('status'));
        $prevAtt = $this->attendanceRate(Attendance::whereIn('class_session_id', $this->sessionIdsIn($pFrom, $pTo))->pluck('status'));

        return [
            'revenue' => $revenue,
            'revenue_change' => $this->delta($revenue, $prevRevenue),
            'enrollments' => $enrolls,
            'enrollments_change' => $this->delta($enrolls, $prevEnrolls),
            'new_students' => User::role(User::ROLE_STUDENT)->whereBetween('created_at', [$from, $to])->count(),
            'active_students' => Enrollment::where('status', 'active')->distinct()->count('user_id'),
            'attendance' => $att,
            'attendance_change' => $att !== null && $prevAtt !== null ? round($att - $prevAtt, 1) : null,
            'classes_held' => ClassSession::whereBetween('starts_at', [$from, $to])->where('status', 'ended')->count(),
            'checkins' => DailyCheckin::whereBetween('created_at', [$from, $to])->count(),
            'tasks_submitted' => TaskSubmission::whereBetween('submitted_at', [$from, $to])->count(),
            'quizzes_taken' => QuizAttempt::whereNotNull('submitted_at')->whereBetween('submitted_at', [$from, $to])->count(),
            'questions' => QaQuestion::whereBetween('created_at', [$from, $to])->count(),
            'certificates' => Certificate::whereNull('revoked_at')->whereBetween('issued_at', [$from, $to])->count(),
            'avg_score' => ($s = Enrollment::where('status', 'active')->avg('score')) !== null ? round((float) $s, 1) : null,
            'at_risk' => Enrollment::where('status', 'active')->where('progress_status', 'at_risk')->count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'pending_amount' => (float) Payment::where('status', 'pending')->sum('amount'),
        ];
    }

    /** Approved revenue for each of the last 12 months (Pakistan months). */
    public function revenueByMonth(int $months = 12): array
    {
        $start = CarbonImmutable::now($this->tz)->startOfMonth()->subMonths($months - 1);
        $rows = Payment::where('status', 'approved')->where('reviewed_at', '>=', $start->utc())->get(['amount', 'refunded_amount', 'reviewed_at'])
            ->groupBy(fn ($p) => $p->reviewed_at->setTimezone($this->tz)->format('Y-m'));

        return collect(range(0, $months - 1))->map(function ($i) use ($start, $rows) {
            $m = $start->addMonths($i);

            return ['label' => $m->format('M y'), 'value' => round((float) $rows->get($m->format('Y-m'), collect())->sum(fn ($p) => (float) $p->amount - (float) ($p->refunded_amount ?? 0)))];
        })->all();
    }

    public function enrollmentsByMonth(int $months = 12): array
    {
        $start = CarbonImmutable::now($this->tz)->startOfMonth()->subMonths($months - 1);
        $rows = Enrollment::where('created_at', '>=', $start->utc())->pluck('created_at')
            ->countBy(fn ($d) => CarbonImmutable::parse($d)->setTimezone($this->tz)->format('Y-m'));

        return collect(range(0, $months - 1))->map(fn ($i) => [
            'label' => $start->addMonths($i)->format('M y'),
            'value' => (int) $rows->get($start->addMonths($i)->format('Y-m'), 0),
        ])->all();
    }

    /** Class attendance rate for each of the last N weeks. */
    public function attendanceByWeek(int $weeks = 12): array
    {
        $start = CarbonImmutable::now($this->tz)->startOfWeek()->subWeeks($weeks - 1);
        $sessions = ClassSession::where('status', 'ended')->where('starts_at', '>=', $start->utc())->get(['id', 'starts_at'])
            ->groupBy(fn ($s) => $s->starts_at->setTimezone($this->tz)->startOfWeek()->format('Y-m-d'));

        return collect(range(0, $weeks - 1))->map(function ($i) use ($start, $sessions) {
            $w = $start->addWeeks($i);
            $ids = $sessions->get($w->format('Y-m-d'), collect())->pluck('id');

            return ['label' => $w->format('d M'), 'value' => $ids->isEmpty() ? null : $this->attendanceRate(Attendance::whereIn('class_session_id', $ids)->pluck('status'))];
        })->all();
    }

    public function checkinsByDay(int $days = 30): array
    {
        $start = CarbonImmutable::now($this->tz)->startOfDay()->subDays($days - 1);
        $rows = DailyCheckin::whereDate('date', '>=', $start->toDateString())->get(['date'])->countBy(fn ($c) => $c->date->format('Y-m-d'));

        return collect(range(0, $days - 1))->map(fn ($i) => [
            'label' => $start->addDays($i)->format('d M'),
            'value' => (int) $rows->get($start->addDays($i)->format('Y-m-d'), 0),
        ])->all();
    }

    public function sources(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $names = ['self' => 'Joined online', 'admin' => 'Added by admin', 'csv' => 'CSV import', 'scholarship' => 'Scholarship session', 'franchise' => 'Franchise'];

        return Enrollment::whereBetween('created_at', [$from, $to])->pluck('source')->countBy()
            ->map(fn ($n, $k) => ['label' => $names[$k] ?? $k, 'value' => $n])->sortByDesc('value')->values()->all();
    }

    public function paymentMethods(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $names = ['jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'bank' => 'Bank transfer', 'cash' => 'Cash', 'other' => 'Other'];

        return Payment::where('status', 'approved')->whereBetween('reviewed_at', [$from, $to])->get(['method', 'amount'])
            ->groupBy('method')->map(fn ($g, $k) => ['label' => $names[$k] ?? ucfirst($k), 'value' => round((float) $g->sum('amount')), 'count' => $g->count()])
            ->sortByDesc('value')->values()->all();
    }

    public function courses(CarbonImmutable $from, CarbonImmutable $to, ?array $only = null): array
    {
        return Course::query()->when($only !== null, fn ($q) => $q->whereIn('id', $only))->orderBy('title')->get(['id', 'title', 'status'])
            ->map(function (Course $c) use ($from, $to) {
                $e = Enrollment::where('course_id', $c->id);
                $active = (clone $e)->where('status', 'active');
                $sessions = ClassSession::where('course_id', $c->id)->where('status', 'ended')->whereBetween('starts_at', [$from, $to])->pluck('id');

                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'status' => $c->status,
                    'active' => (clone $active)->count(),
                    'new' => (clone $e)->whereBetween('created_at', [$from, $to])->count(),
                    'revenue' => $this->revenueIn($from, $to, [$c->id]),
                    'attendance' => $this->attendanceRate(Attendance::whereIn('class_session_id', $sessions)->pluck('status')),
                    'avg_score' => ($s = (clone $active)->avg('score')) !== null ? round((float) $s, 1) : null,
                    'at_risk' => (clone $active)->where('progress_status', 'at_risk')->count(),
                    'completed' => (clone $e)->where('status', 'completed')->count(),
                ];
            })->sortByDesc('active')->values()->all();
    }

    public function batches(?array $courseIds = null): array
    {
        return Batch::with('courses:id,title', 'mentor:id,name')
            ->when($courseIds !== null, fn ($q) => $q->whereHas('courses', fn ($c) => $c->whereIn('courses.id', $courseIds)))
            ->whereIn('status', ['upcoming', 'running', 'completed'])->where(fn ($q) => $q->where('status', '!=', 'completed')->orWhere('ends_on', '>=', now()->subMonths(3)))
            ->orderByDesc('starts_on')->limit(40)->get()
            ->map(function (Batch $b) {
                $e = Enrollment::where('batch_id', $b->id)->whereIn('status', ['active', 'completed']);
                $sessions = ClassSession::where('batch_id', $b->id)->where('status', 'ended')->pluck('id');

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'courses' => $b->courses->pluck('title')->implode(', '),
                    'mentor' => $b->mentor?->name,
                    'status' => $b->status,
                    'students' => (clone $e)->distinct()->count('user_id'),
                    'classes' => $sessions->count(),
                    'attendance' => $this->attendanceRate(Attendance::whereIn('class_session_id', $sessions)->pluck('status')),
                    'avg_score' => ($s = (clone $e)->avg('score')) !== null ? round((float) $s, 1) : null,
                    'at_risk' => (clone $e)->where('status', 'active')->where('progress_status', 'at_risk')->count(),
                    'certified' => (clone $e)->where('status', 'completed')->count(),
                ];
            })->all();
    }

    /** How each mentor is doing: classes held, attendance, how fast they check tasks and answer questions. */
    public function mentors(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return User::role(User::ROLE_MENTOR)->with('mentoredCourses:id')->orderBy('name')->get(['id', 'name'])
            ->map(function (User $m) use ($from, $to) {
                $courseIds = $m->mentoredCourses->pluck('id');
                $sessions = ClassSession::where('mentor_id', $m->id)->where('kind', 'live')->where('status', 'ended')->whereBetween('starts_at', [$from, $to])->pluck('id');
                $graded = TaskSubmission::where('graded_by', $m->id)->whereBetween('graded_at', [$from, $to])->get(['submitted_at', 'graded_at']);
                $waiting = TaskSubmission::where('status', 'submitted')->whereHas('task', fn ($t) => $t->whereIn('course_id', $courseIds))->count();
                $answers = QaAnswer::where('user_id', $m->id)->whereBetween('created_at', [$from, $to])->count();
                $unanswered = QaQuestion::whereIn('course_id', $courseIds)->where('status', 'open')->where('answers_count', 0)->count();
                $checkHours = $graded->map(fn ($s) => $s->submitted_at->diffInMinutes($s->graded_at, true) / 60);

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'students' => Enrollment::whereIn('course_id', $courseIds)->where('status', 'active')->distinct()->count('user_id'),
                    'classes' => $sessions->count(),
                    'attendance' => $this->attendanceRate(Attendance::whereIn('class_session_id', $sessions)->pluck('status')),
                    'graded' => $graded->count(),
                    'check_hours' => $checkHours->isEmpty() ? null : round($checkHours->median(), 1),
                    'waiting' => $waiting,
                    'answers' => $answers,
                    'unanswered' => $unanswered,
                    'avg_score' => ($s = Enrollment::whereIn('course_id', $courseIds)->where('status', 'active')->avg('score')) !== null ? round((float) $s, 1) : null,
                ];
            })->all();
    }

    /** Students brought by each franchise partner. */
    public function franchise(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return User::role(User::ROLE_FRANCHISER)->orderBy('name')->get(['id', 'name', 'city'])
            ->map(function (User $f) use ($from, $to) {
                $e = Enrollment::where('franchiser_id', $f->id);

                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'city' => $f->city,
                    'students' => (clone $e)->distinct()->count('user_id'),
                    'new' => (clone $e)->whereBetween('created_at', [$from, $to])->count(),
                    'active' => (clone $e)->where('status', 'active')->count(),
                    'pending' => (clone $e)->where('status', 'pending_payment')->count(),
                    'completed' => (clone $e)->where('status', 'completed')->count(),
                    'revenue' => (float) Payment::where('status', 'approved')->whereBetween('reviewed_at', [$from, $to])
                        ->whereIn('enrollment_id', (clone $e)->select('id'))->sum('amount'),
                ];
            })->all();
    }

    /** Short numbers-only digest used by the AI summary and the weekly email. */
    public function digest(CarbonImmutable $from, CarbonImmutable $to, string $label): array
    {
        return [
            'period' => $label,
            'kpis' => $this->kpis($from, $to),
            'courses' => collect($this->courses($from, $to))->map(fn ($c) => collect($c)->except('id', 'status')->all())->take(15)->all(),
            'mentors' => collect($this->mentors($from, $to))->map(fn ($m) => collect($m)->except('id')->all())->all(),
            'sources' => $this->sources($from, $to),
            'payment_methods' => $this->paymentMethods($from, $to),
        ];
    }
}
