<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\DailyCheckin;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Task;
use App\Models\TaskSubmission;
use Carbon\CarbonImmutable;

/**
 * A student's result in one course, from the course's completion rules:
 * attendance (live + recorded classes), tasks (marks), quizzes (best score).
 * Parts with nothing to count yet are left out and the weights are shared by the rest.
 */
class ProgressService
{
    /** @return array{attendance:?float, tasks:?float, quiz:?float, score:?float, status:string, notes:array, eligible:bool, counts:array} */
    public function calculate(Enrollment $e): array
    {
        $course = $e->course;
        $userId = $e->user_id;
        $now = CarbonImmutable::now();

        // ---- Attendance: classes of this batch + course that are over ----
        $sessions = ClassSession::where('course_id', $e->course_id)
            ->when($e->batch_id, fn ($q) => $q->where('batch_id', $e->batch_id), fn ($q) => $q->whereRaw('1 = 0'))
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('status', 'ended')->orWhere('starts_at', '<', $now->subHours(3)))
            ->pluck('id');
        $marks = Attendance::whereIn('class_session_id', $sessions)->where('user_id', $userId)->pluck('status');
        $attended = $marks->filter(fn ($s) => $s === 'present')->count() + 0.5 * $marks->filter(fn ($s) => $s === 'partial')->count();
        $attendance = $sessions->count() ? round($attended * 100 / $sessions->count(), 1) : null;

        // ---- Tasks: graded marks; missed (past due, not submitted) count as 0 ----
        $tasks = Task::where('course_id', $e->course_id)->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('batch_id')->orWhere('batch_id', $e->batch_id))->get(['id', 'max_marks', 'due_at']);
        $subs = TaskSubmission::whereIn('task_id', $tasks->pluck('id'))->where('user_id', $userId)->get()->keyBy('task_id');
        $got = 0;
        $max = 0;
        $missed = 0;
        foreach ($tasks as $t) {
            $s = $subs->get($t->id);
            if ($s && $s->status === 'graded') {
                $got += (float) $s->marks;
                $max += $t->max_marks;
            } elseif (! $s && $t->due_at && $t->due_at->isPast()) {
                $max += $t->max_marks;
                $missed++;
            }
            // Submitted but not checked yet, or not due yet: not counted yet.
        }
        $tasksPercent = $max > 0 ? round($got * 100 / $max, 1) : null;

        // ---- Quizzes: best attempt; closed and never taken = 0 ----
        $quizzes = Quiz::where('course_id', $e->course_id)->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('batch_id')->orWhere('batch_id', $e->batch_id))->get(['id', 'due_at']);
        $best = QuizAttempt::whereIn('quiz_id', $quizzes->pluck('id'))->where('user_id', $userId)->whereNotNull('submitted_at')
            ->selectRaw('quiz_id, max(percent) as best')->groupBy('quiz_id')->pluck('best', 'quiz_id');
        $quizScores = [];
        foreach ($quizzes as $q) {
            if ($best->has($q->id)) {
                $quizScores[] = (float) $best[$q->id];
            } elseif ($q->due_at && $q->due_at->isPast()) {
                $quizScores[] = 0.0;
            }
        }
        $quizPercent = $quizScores ? round(array_sum($quizScores) / count($quizScores), 1) : null;

        // ---- Weighted score ----
        $parts = array_filter([
            [$attendance, (int) $course->weight_attendance],
            [$tasksPercent, (int) $course->weight_tasks],
            [$quizPercent, (int) $course->weight_quiz],
        ], fn ($p) => $p[0] !== null && $p[1] > 0);
        $weights = array_sum(array_column($parts, 1));
        $score = $weights > 0 ? round(array_sum(array_map(fn ($p) => $p[0] * $p[1], $parts)) / $weights, 1) : null;

        // ---- Rules → eligible / at risk ----
        $notes = [];
        if ($attendance !== null && $attendance < $course->min_attendance_percent) {
            $notes[] = "Attendance {$attendance}% (needs {$course->min_attendance_percent}%)";
        }
        if ($quizPercent !== null && $quizPercent < $course->min_quiz_percent) {
            $notes[] = "Quiz average {$quizPercent}% (needs {$course->min_quiz_percent}%)";
        }
        $pendingTasks = $tasks->filter(fn ($t) => ! $subs->has($t->id))->count();
        if ($course->require_compulsory_tasks && $pendingTasks > 0) {
            $notes[] = "{$pendingTasks} task(s) not submitted";
        }
        $ungraded = $subs->where('status', '!=', 'graded')->count();
        if ($ungraded > 0) {
            $notes[] = "{$ungraded} task(s) waiting to be checked";
        }
        // Everything above blocks the certificate; "no activity" below is only a warning.
        $hasAnything = $attendance !== null || $tasksPercent !== null || $quizPercent !== null;
        $eligible = $hasAnything && $notes === [];

        // At risk: clearly behind while the course is running.
        $inactiveDays = $this->daysSinceActivity($e);
        $atRisk = ! $eligible && (
            ($attendance !== null && $sessions->count() >= 3 && $attendance < max(0, $course->min_attendance_percent - 15))
            || $missed >= 2
            || ($quizPercent !== null && $quizPercent < $course->min_quiz_percent && count($quizScores) >= 2)
            || ($inactiveDays !== null && $inactiveDays >= 7)
        );
        if ($inactiveDays !== null && $inactiveDays >= 7) {
            $notes[] = "No activity for {$inactiveDays} days";
        }

        return [
            'attendance' => $attendance,
            'tasks' => $tasksPercent,
            'quiz' => $quizPercent,
            'score' => $score,
            'eligible' => $eligible,
            'status' => $eligible ? 'eligible' : ($atRisk ? 'at_risk' : 'on_track'),
            'notes' => array_values($notes),
            'counts' => [
                'classes' => $sessions->count(), 'attended' => $attended,
                'tasks' => $tasks->count(), 'tasks_done' => $subs->count(), 'tasks_missed' => $missed,
                'quizzes' => $quizzes->count(), 'quizzes_taken' => $best->count(),
            ],
        ];
    }

    /** Calculates and saves the result on the enrollment. */
    public function refresh(Enrollment $e): array
    {
        $r = $this->calculate($e->loadMissing('course'));
        $e->forceFill([
            'attendance_percent' => $r['attendance'],
            'tasks_percent' => $r['tasks'],
            'quiz_percent' => $r['quiz'],
            'score' => $r['score'],
            'progress_status' => $e->status === 'completed' ? 'eligible' : $r['status'],
            'progress_notes' => $r['notes'],
            'progress_updated_at' => now(),
        ])->saveQuietly();

        return $r;
    }

    /** Days since the student last did anything in the portal for learning (null = never started). */
    private function daysSinceActivity(Enrollment $e): ?int
    {
        $since = $e->activated_at ?? $e->created_at;
        if (! $since || $since->gt(now()->subDays(7))) {
            return null; // just joined
        }
        $last = collect([
            DailyCheckin::where('user_id', $e->user_id)->max('created_at'),
            TaskSubmission::where('user_id', $e->user_id)->max('submitted_at'),
            QuizAttempt::where('user_id', $e->user_id)->max('started_at'),
            Attendance::where('user_id', $e->user_id)->whereIn('status', ['present', 'partial'])->max('updated_at'),
        ])->filter()->map(fn ($d) => CarbonImmutable::parse($d))->max();

        return (int) floor(($last ?? CarbonImmutable::parse($since))->diffInDays(now(), true));
    }
}
