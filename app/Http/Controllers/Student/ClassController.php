<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\SessionParticipant;
use App\Services\Phone;
use App\Services\Zoom\ZoomSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Student: my live classes, join inside the portal, heartbeat for single-device checks. */
class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tz = config('lwe.timezone');
        $attendance = Attendance::where('user_id', $user->id)->pluck('status', 'class_session_id');

        $map = fn (ClassSession $s) => [
            'id' => $s->id,
            'title' => $s->title,
            'kind' => $s->kind,
            'course' => $s->course->title,
            'batch' => $s->batch->name,
            'mentor' => $s->mentor?->name,
            'status' => $s->status,
            'label' => $s->starts_at->setTimezone($tz)->format('D d M, g:i A'),
            'starts_at_iso' => $s->starts_at->toIso8601String(),
            'join_opens_iso' => $s->joinOpensAt()->toIso8601String(),
            'ends_iso' => $s->endsAt()->addMinutes(30)->toIso8601String(),
            'can_join' => $s->isJoinable(),
            'attendance' => $attendance->get($s->id),
            'notes' => $s->notes_published ? $s->ai_notes : null,
            'cancel_reason' => $s->cancel_reason,
        ];

        $base = ClassSession::with(['course:id,title', 'batch:id,name', 'mentor:id,name'])->forStudent($user->id);

        return Inertia::render('Student/Classes/Index', [
            'upcoming' => (clone $base)->whereIn('status', ['scheduled', 'live'])->where('starts_at', '>=', now()->subHours(4))->orderBy('starts_at')->limit(30)->get()->map($map),
            'past' => (clone $base)->where(fn ($q) => $q->whereIn('status', ['ended', 'cancelled'])->orWhere('starts_at', '<', now()->subHours(4)))
                ->orderByDesc('starts_at')->limit(30)->get()->map($map),
        ]);
    }

    /** The class room page: Zoom runs inside the portal (no Zoom link is ever shown). */
    public function join(Request $request, ClassSession $session): Response|RedirectResponse
    {
        $user = $request->user();
        $enrolled = Enrollment::where('user_id', $user->id)->where('batch_id', $session->batch_id)->where('course_id', $session->course_id)
            ->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($enrolled || $user->isStaff(), 403);

        if (! $session->isJoinable()) {
            $msg = match (true) {
                $session->status === 'cancelled' => 'This class was cancelled.',
                $session->isRecorded() && now()->gte($session->endsAt()) => 'This lecture has finished. Watch it any time from the course lessons.',
                $session->status === 'ended' => 'This class has ended.',
                ! $session->isRecorded() && ! $session->zoom_meeting_id => 'This class is not ready yet. Please contact support.',
                default => 'Join opens '.(int) \App\Models\Setting::get('join_before_minutes').' minutes before class ('.$session->joinOpensAt()->setTimezone(config('lwe.timezone'))->format('g:i A').').',
            };

            return redirect()->route('student.classes.index')->with('error', $msg);
        }

        if ($session->isRecorded()) {
            $this->logWatch($session, $user->id);

            return Inertia::render('Classes/Replay', [
                'session' => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'course' => $session->course->title,
                    'batch' => $session->batch->name,
                    'video_url' => $session->video_url,
                    'starts_iso' => $session->starts_at->toIso8601String(),
                    'ends_iso' => $session->endsAt()->toIso8601String(),
                    'offset' => max(0, (int) $session->starts_at->diffInSeconds(now(), false)),
                    'lesson' => $session->lesson?->only('id', 'title', 'summary'),
                ],
                'backUrl' => route('student.classes.index'),
            ]);
        }

        return Inertia::render('Classes/Room', $this->roomProps($session, $user, 0));
    }

    /** Recorded lecture: the page pings every 30 s while it is open and playing → attendance minutes. */
    public function watch(Request $request, ClassSession $session): HttpResponse
    {
        $user = $request->user();
        abort_unless($session->isRecorded(), 404);
        $enrolled = Enrollment::where('user_id', $user->id)->where('batch_id', $session->batch_id)->where('course_id', $session->course_id)
            ->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($enrolled, 403);
        if (now()->lt($session->starts_at->subMinutes(10)) || now()->gt($session->endsAt()->addMinutes(2))) {
            return response()->noContent();
        }
        $this->logWatch($session, $user->id);

        return response()->noContent();
    }

    /** Extends the current watch interval, or starts a new one after a gap of more than 90 s. */
    private function logWatch(ClassSession $session, int $userId): void
    {
        $now = now();
        $last = SessionParticipant::where('class_session_id', $session->id)->where('user_id', $userId)->latest('id')->first();
        if ($last && $last->left_at && $last->left_at->gte($now->copy()->subSeconds(90))) {
            $last->update(['left_at' => $now]);
        } else {
            SessionParticipant::create([
                'class_session_id' => $session->id, 'user_id' => $userId,
                'display_name' => 'portal player', 'joined_at' => $now, 'left_at' => $now,
            ]);
        }
    }

    /** Polled every 30 s by the room. EnsureSingleSession logs out an old device before this runs. */
    public function heartbeat(): HttpResponse
    {
        return response()->noContent();
    }

    public static function roomProps(ClassSession $session, $user, int $role, ?string $zak = null): array
    {
        $account = $session->zoomAccount;
        $display = mb_substr($user->name, 0, 40).' – '.$user->student_code;

        return [
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'course' => $session->course->title,
                'batch' => $session->batch->name,
                'ends_iso' => $session->endsAt()->toIso8601String(),
            ],
            'zoom' => [
                'sdkKey' => $account->sdk_key,
                'signature' => ZoomSignature::make($account->sdk_key, $account->sdk_secret, $session->zoom_meeting_id, $role),
                'meetingNumber' => $session->zoom_meeting_id,
                'password' => (string) $session->zoom_password,
                'userName' => $display,
                'userEmail' => $role === 1 ? $account->host_email : '',
                'customerKey' => (string) $user->id,   // comes back in webhooks → attendance
                'zak' => $zak,
            ],
            'role' => $role,
            'watermark' => $user->name.' · '.$user->student_code.' · '.(Phone::masked($user->phone) ?? ''),
            'backUrl' => $role === 1 ? route('mentor.dashboard') : route('student.classes.index'),
        ];
    }
}
