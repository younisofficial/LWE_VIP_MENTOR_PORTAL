<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\ClassReminder;
use App\Models\ClassSession;
use App\Models\Setting;
use App\Notifications\ClassReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Runs every minute (scheduler). Sends each reminder once per student:
 * 1 day before, 1 hour before, when Join opens, and "you missed it" after class.
 */
class SendClassReminders extends Command
{
    protected $signature = 'lwe:class-reminders';

    protected $description = 'Send class reminders (WhatsApp + in-app)';

    public function handle(): int
    {
        $now = now();
        $joinBefore = (int) Setting::get('join_before_minutes');
        $sent = 0;

        // Safety net: a live class whose Zoom meeting was never made (e.g. the page closed early) gets one now.
        ClassSession::where('kind', '!=', 'recorded')->where('status', 'scheduled')->whereNull('zoom_meeting_id')->whereNull('zoom_error')
            ->whereNotNull('zoom_account_id')->whereBetween('starts_at', [$now->copy()->subHour(), $now->copy()->addDays(30)])
            ->where('created_at', '<=', $now->copy()->subMinutes(3))->orderBy('starts_at')->limit(20)->get()
            ->each(fn ($s) => \App\Jobs\SyncZoomMeeting::dispatchSync($s, 'create'));

        // Recorded lectures have no Zoom to say "started/ended": move them on by the clock.
        ClassSession::where('kind', 'recorded')->where('status', 'scheduled')->where('starts_at', '<=', $now)
            ->get()->each(fn ($s) => $now->lt($s->endsAt()) ? $s->update(['status' => 'live', 'started_at' => $s->starts_at]) : null);
        ClassSession::where('kind', 'recorded')->whereIn('status', ['scheduled', 'live'])->where('starts_at', '<=', $now)->get()
            ->filter(fn ($s) => $now->gte($s->endsAt()))
            ->each(function ($s) {
                $s->update(['status' => 'ended', 'started_at' => $s->started_at ?? $s->starts_at, 'ended_at' => $s->endsAt()]);
                \App\Jobs\ComputeAttendance::dispatchSync($s);
            });

        $upcoming = ClassSession::with('mentor')->where('status', 'scheduled')
            ->whereBetween('starts_at', [$now, $now->copy()->addHours(25)])
            ->get();

        foreach ($upcoming as $session) {
            $minutes = $now->diffInMinutes($session->starts_at, false);
            $kind = match (true) {
                $minutes <= $joinBefore && Setting::get('reminder_start') => 'start',
                $minutes <= 60 && $minutes > $joinBefore && Setting::get('reminder_hour') => 'hour',
                $minutes <= 24 * 60 && $minutes > 60 && Setting::get('reminder_day') => 'day',
                default => null,
            };
            if ($kind) {
                $sent += $this->send($session, $kind, $session->expectedStudents()->get());
                // The mentor also gets the day and hour reminders.
                if (in_array($kind, ['day', 'hour'], true) && $session->mentor) {
                    $sent += $this->send($session, $kind, collect([$session->mentor]));
                }
            }
        }

        if (Setting::get('reminder_missed')) {
            $ended = ClassSession::where('status', 'ended')->where('ended_at', '>=', $now->copy()->subDay())->get();
            foreach ($ended as $session) {
                $absentIds = Attendance::where('class_session_id', $session->id)->where('status', 'absent')->pluck('user_id');
                $sent += $this->send($session, 'missed', $session->expectedStudents()->whereIn('id', $absentIds)->get());
            }
        }

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function send(ClassSession $session, string $kind, $students): int
    {
        $count = 0;
        foreach ($students as $student) {
            try {
                // The unique (session, user, kind) row is the "already sent" lock.
                ClassReminder::create(['class_session_id' => $session->id, 'user_id' => $student->id, 'kind' => $kind]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
            $student->notify(new ClassReminderNotification($session, $kind));
            $count++;
        }

        return $count;
    }
}
