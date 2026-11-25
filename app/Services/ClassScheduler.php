<?php

namespace App\Services;

use App\Jobs\DeleteZoomMeeting;
use App\Jobs\SyncZoomMeeting;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Lesson;
use App\Models\User;
use App\Models\ZoomAccount;
use App\Notifications\ClassChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Schedules live classes: picks a free Zoom account, blocks double-booking of accounts
 * and mentors, creates the Zoom meetings, and handles reschedule/cancel.
 */
class ClassScheduler
{
    /** Minutes kept free between two meetings on the same Zoom account. */
    private const GAP = 10;

    /**
     * @param  array{course_id?:int, start_date:string, time:string, duration:int, days:array, weeks:int, mentor_id:?int, start_lesson_id:?int, title:?string}  $p
     * @return Collection<ClassSession>
     */
    public function schedule(Batch $batch, array $p, User $by): Collection
    {
        $tz = config('lwe.timezone');
        $first = CarbonImmutable::parse($p['start_date'].' '.$p['time'], $tz);
        $days = array_values(array_intersect(Batch::DAYS, $p['days'] ?: [strtolower($first->format('D'))]));
        $mentorId = $p['mentor_id'] ?? $batch->mentor_id;

        // All class times (local), then UTC.
        $times = [];
        $cursor = $first->startOfDay();
        $last = $first->addWeeks(max(1, (int) $p['weeks']))->startOfDay();
        while ($cursor->lt($last) && count($times) < 200) {
            if (in_array(strtolower($cursor->format('D')), $days, true)) {
                $times[] = CarbonImmutable::parse($cursor->format('Y-m-d').' '.$p['time'], $tz)->utc();
            }
            $cursor = $cursor->addDay();
        }
        if (! $times) {
            throw ValidationException::withMessages(['days' => 'No class dates fall in this range. Check the days and weeks.']);
        }
        if ($times[0]->isPast()) {
            throw ValidationException::withMessages(['start_date' => 'The first class is in the past.']);
        }

        // Which course of the batch these classes are for (a batch can hold several courses).
        $courseId = (int) ($p['course_id'] ?? $batch->course_id);
        $course = $batch->courses()->whereKey($courseId)->first();
        if (! $course) {
            throw ValidationException::withMessages(['course_id' => 'Choose a course that is part of this batch.']);
        }

        $recorded = ($p['kind'] ?? 'live') === 'recorded';

        // Lessons in course order, starting from the chosen one (one lesson per class).
        $lessons = $this->lessonsFrom($course->id, $p['start_lesson_id'] ?? null, $recorded);
        if ($recorded) {
            // Recorded lectures: only lessons that have a video, and no more classes than videos.
            if ($lessons->isEmpty()) {
                throw ValidationException::withMessages(['start_lesson_id' => 'Choose the first recorded lesson. Only lessons with a video link can be auto-played.']);
            }
            $times = array_slice($times, 0, $lessons->count());
        }

        $sessions = DB::transaction(function () use ($batch, $course, $p, $times, $mentorId, $lessons, $recorded) {
            $seriesId = (string) Str::uuid();
            $made = collect();
            foreach ($times as $i => $start) {
                $lesson = $lessons->get($i);
                $duration = $recorded ? max(5, (int) ($lesson->duration_minutes ?: $p['duration'])) : (int) $p['duration'];
                if (! $recorded) {
                    $this->assertMentorFree($mentorId, $start, $duration);
                }
                $account = $recorded ? null : $this->pickAccount($batch, $start, $duration);

                $made->push(ClassSession::create([
                    'course_id' => $course->id,
                    'batch_id' => $batch->id,
                    'lesson_id' => $lesson?->id,
                    'mentor_id' => $mentorId,
                    'zoom_account_id' => $account?->id,
                    'series_id' => $seriesId,
                    'kind' => $recorded ? 'recorded' : 'live',
                    'video_url' => $recorded ? $lesson->video_url : null,
                    'title' => trim(($p['title'] ?? '') ?: ($lesson?->title ?? $course->title.' — Live class')),
                    'starts_at' => $start,
                    'duration_minutes' => $duration,
                    'status' => 'scheduled',
                ]));
            }

            return $made;
        });

        // Create the Zoom meetings (queued; runs right away when QUEUE_CONNECTION=sync).
        $sessions->reject->isRecorded()->each(fn (ClassSession $s) => SyncZoomMeeting::dispatchAfterResponse($s, 'create'));

        // Tell the students and the mentor (in-app + email): one message for the whole series.
        $first = $sessions->first()->load('course:id,title', 'batch:id,name', 'mentor');
        $dates = $sessions->map(fn (ClassSession $s) => $s->starts_at->setTimezone(config('lwe.timezone'))->format('D d M Y, g:i A'))->all();
        Notification::send($this->audience($first), new \App\Notifications\ClassesScheduled($first, $dates));

        activity('classes')->causedBy($by)->performedOn($batch)->withProperties(['count' => $sessions->count()])->log('classes.scheduled');

        return $sessions;
    }

    public function reschedule(ClassSession $session, string $date, string $time, int $duration, User $by): void
    {
        $start = CarbonImmutable::parse($date.' '.$time, config('lwe.timezone'))->utc();
        if ($start->isPast()) {
            throw ValidationException::withMessages(['date' => 'The new time is in the past.']);
        }

        if ($session->isRecorded()) {
            $session->update(['starts_at' => $start, 'duration_minutes' => $duration]);
            \App\Models\ClassReminder::where('class_session_id', $session->id)->whereIn('kind', ['day', 'hour', 'start'])->delete();
            Notification::send($this->audience($session), new ClassChanged($session, 'rescheduled'));
            activity('classes')->causedBy($by)->performedOn($session)->log('class.rescheduled');

            return;
        }

        $this->assertMentorFree($session->mentor_id, $start, $duration, $session->id);
        $account = $session->zoomAccount && $this->accountFree($session->zoomAccount, $start, $duration, $session->id)
            ? $session->zoomAccount
            : $this->pickAccount($session->batch, $start, $duration, $session->id);

        $accountChanged = $account->id !== $session->zoom_account_id;
        $old = [$session->zoom_account_id, $session->zoom_meeting_id];

        $session->update(['starts_at' => $start, 'duration_minutes' => $duration, 'zoom_account_id' => $account->id]);

        if ($accountChanged && $old[1]) {
            // Meeting moves to another Zoom account: delete the old one, create a new one.
            DeleteZoomMeeting::dispatch($old[0], $old[1]);
            $session->forceFill(['zoom_meeting_id' => null, 'zoom_password' => null])->save();
            SyncZoomMeeting::dispatchAfterResponse($session, 'create');
        } else {
            SyncZoomMeeting::dispatchAfterResponse($session, $session->zoom_meeting_id ? 'update' : 'create');
        }

        // Fresh reminders for the new time.
        \App\Models\ClassReminder::where('class_session_id', $session->id)->whereIn('kind', ['day', 'hour', 'start'])->delete();
        Notification::send($this->audience($session), new ClassChanged($session, 'rescheduled'));
        activity('classes')->causedBy($by)->performedOn($session)->log('class.rescheduled');
    }

    public function cancel(ClassSession $session, string $reason, User $by): void
    {
        $session->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
        if ($session->zoom_meeting_id && $session->zoom_account_id) {
            DeleteZoomMeeting::dispatch($session->zoom_account_id, $session->zoom_meeting_id);
        }
        Notification::send($this->audience($session), new ClassChanged($session, 'cancelled'));
        activity('classes')->causedBy($by)->performedOn($session)->log('class.cancelled');
    }

    // ------------------------------------------------------------------

    private function lessonsFrom(int $courseId, ?int $startLessonId, bool $withVideoOnly = false): Collection
    {
        if (! $startLessonId) {
            return collect();
        }
        $ordered = Lesson::where('lessons.course_id', $courseId)
            ->join('course_modules', 'course_modules.id', '=', 'lessons.course_module_id')
            ->orderBy('course_modules.position')->orderBy('lessons.position')
            ->select('lessons.*')->get();
        $index = $ordered->search(fn ($l) => $l->id === $startLessonId);

        $from = $index === false ? collect() : $ordered->slice($index)->values();

        return $withVideoOnly ? $from->filter(fn ($l) => filled($l->video_url))->values() : $from;
    }

    /** Batch's preferred account first, then any active account that is free at that time. */
    private function pickAccount(Batch $batch, CarbonImmutable $start, int $duration, ?int $ignoreSessionId = null): ZoomAccount
    {
        $accounts = ZoomAccount::where('is_active', true)->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$batch->zoom_account_id ?? 0])->orderBy('id')->get();
        if ($accounts->isEmpty()) {
            throw ValidationException::withMessages(['zoom' => 'No Zoom account is connected. Add one in Admin → Zoom Accounts.']);
        }
        foreach ($accounts as $account) {
            if ($this->accountFree($account, $start, $duration, $ignoreSessionId)) {
                return $account;
            }
        }

        throw ValidationException::withMessages([
            'zoom' => 'All Zoom accounts are busy on '.$start->setTimezone(config('lwe.timezone'))->format('D d M, g:i A')
                .'. Choose another time or add another Zoom account.',
        ]);
    }

    private function accountFree(ZoomAccount $account, CarbonImmutable $start, int $duration, ?int $ignoreSessionId = null): bool
    {
        return ! $this->overlapping($start, $duration, $ignoreSessionId)->where('zoom_account_id', $account->id)->exists();
    }

    private function assertMentorFree(?int $mentorId, CarbonImmutable $start, int $duration, ?int $ignoreSessionId = null): void
    {
        if ($mentorId && $this->overlapping($start, $duration, $ignoreSessionId)->where('mentor_id', $mentorId)->where('kind', 'live')->exists()) {
            throw ValidationException::withMessages([
                'mentor_id' => 'The mentor already has a class at '.$start->setTimezone(config('lwe.timezone'))->format('D d M, g:i A').'.',
            ]);
        }
    }

    /** Non-cancelled sessions overlapping [start - GAP, end + GAP]. Overlap check done in PHP (DB-agnostic). */
    private function overlapping(CarbonImmutable $start, int $duration, ?int $ignoreSessionId)
    {
        $from = $start->subMinutes(self::GAP);
        $to = $start->addMinutes($duration + self::GAP);
        $ids = ClassSession::whereIn('status', ['scheduled', 'live'])
            ->where('starts_at', '<', $to)
            ->where('starts_at', '>', $from->subHours(12)) // longest class assumption: 12h
            ->when($ignoreSessionId, fn ($q) => $q->where('id', '!=', $ignoreSessionId))
            ->get(['id', 'starts_at', 'duration_minutes'])
            ->filter(fn ($s) => $s->starts_at->lt($to) && $s->endsAt()->gt($from))
            ->pluck('id');

        return ClassSession::whereIn('id', $ids);
    }

    /** Students of the class + its mentor. */
    private function audience(ClassSession $session): Collection
    {
        $people = $session->expectedStudents()->get();
        if ($session->mentor_id && ($mentor = User::find($session->mentor_id))) {
            $people->push($mentor);
        }

        return $people->unique('id')->values();
    }
}
