<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Task;
use App\Notifications\StudentAtRisk;
use App\Notifications\TaskDueSoon;
use App\Services\CertificateService;
use App\Services\ProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Module 4 automation.
 *  daily (7 AM PK): recalculate every active student's result, issue certificates when a batch has finished
 *                   and the rules are met, and send a friendly nudge to students falling behind (max once a week).
 *  hourly (--tasks): remind students who have not submitted a task that is due within a day.
 */
class RunAutomation extends Command
{
    protected $signature = 'lwe:automation {--tasks : Only the task-due reminders}';

    protected $description = 'Progress, certificates, at-risk nudges and task reminders';

    public function handle(ProgressService $progress, CertificateService $certificates): int
    {
        $this->taskReminders();
        if ($this->option('tasks')) {
            return self::SUCCESS;
        }

        $today = now(config('lwe.timezone'))->toDateString();
        $updated = $issued = $nudged = 0;

        Enrollment::with(['course', 'batch', 'user', 'certificate'])->where('status', 'active')
            ->chunkById(200, function ($chunk) use ($progress, $certificates, $today, &$updated, &$issued, &$nudged) {
                foreach ($chunk as $e) {
                    $r = $progress->refresh($e);
                    $updated++;

                    $batchFinished = $e->batch && ($e->batch->status === 'completed' || ($e->batch->ends_on && $e->batch->ends_on->toDateString() < $today));
                    if ($r['eligible'] && $batchFinished && ! $e->certificate && Setting::get('auto_certificates')) {
                        $certificates->issue($e);
                        $issued++;

                        continue;
                    }

                    if ($r['status'] === 'at_risk' && Setting::get('auto_nudges')
                        && (! $e->last_nudged_at || $e->last_nudged_at->lt(now()->subDays(7)))) {
                        $e->user->notify(new StudentAtRisk($e));
                        $e->forceFill(['last_nudged_at' => now()])->saveQuietly();
                        $nudged++;
                    }
                }
            });

        $this->info("Progress updated: {$updated} · certificates issued: {$issued} · nudges sent: {$nudged}");

        return self::SUCCESS;
    }

    private function taskReminders(): void
    {
        $tasks = Task::with('course:id,title')->where('status', 'published')
            ->whereBetween('due_at', [now(), now()->addHours(24)])->get();

        $sent = 0;
        foreach ($tasks as $task) {
            $waiting = $task->audience()->whereDoesntHave('taskSubmissions', fn ($q) => $q->where('task_id', $task->id))->get();
            foreach ($waiting as $student) {
                // Once per task per student.
                if (Cache::add("task-due:{$task->id}:{$student->id}", 1, now()->addDays(3))) {
                    $student->notify(new TaskDueSoon($task));
                    $sent++;
                }
            }
        }
        $this->line("Task reminders sent: {$sent}");
    }
}
