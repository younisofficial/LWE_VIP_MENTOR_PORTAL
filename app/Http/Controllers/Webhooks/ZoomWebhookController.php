<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeAttendance;
use App\Jobs\ImportZoomRecording;
use App\Models\ClassSession;
use App\Models\SessionParticipant;
use App\Models\User;
use App\Models\ZoomAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Zoom → our portal. Every request is checked with the account's webhook secret:
 * x-zm-signature = "v0=" + HMAC-SHA256(secret, "v0:{x-zm-request-timestamp}:{raw body}").
 * Zoom expects an answer within 3 seconds, so heavy work goes to jobs.
 */
class ZoomWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $account = $this->verifiedAccount($request, $raw);
        if (! $account) {
            Log::warning('Zoom webhook rejected: bad signature');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $body = json_decode($raw, true) ?: [];
        $event = $body['event'] ?? '';
        $object = $body['payload']['object'] ?? [];

        if ($event === 'endpoint.url_validation') {
            $plain = (string) ($body['payload']['plainToken'] ?? '');

            return response()->json([
                'plainToken' => $plain,
                'encryptedToken' => hash_hmac('sha256', $plain, $account->webhook_secret),
            ]);
        }

        $session = isset($object['id'])
            ? ClassSession::where('zoom_meeting_id', (string) $object['id'])->where('zoom_account_id', $account->id)->first()
            : null;
        if (! $session) {
            return response()->json(['ok' => true]); // not one of our classes
        }

        match ($event) {
            'meeting.started' => $session->update(['status' => 'live', 'started_at' => $this->time($object['start_time'] ?? null)]),
            'meeting.ended' => $this->ended($session, $object),
            'meeting.participant_joined' => $this->joined($session, $object['participant'] ?? []),
            'meeting.participant_left' => $this->left($session, $object['participant'] ?? []),
            'recording.completed' => ImportZoomRecording::dispatch($session, $object, $body['download_token'] ?? null)->afterResponse(),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    private function verifiedAccount(Request $request, string $raw): ?ZoomAccount
    {
        $signature = (string) $request->header('x-zm-signature');
        $timestamp = (string) $request->header('x-zm-request-timestamp');
        if ($signature === '' || $timestamp === '' || abs(time() - (int) $timestamp) > 300) {
            return null;
        }

        foreach (ZoomAccount::where('is_active', true)->get() as $account) {
            if ($account->webhook_secret && hash_equals('v0='.hash_hmac('sha256', "v0:{$timestamp}:{$raw}", $account->webhook_secret), $signature)) {
                return $account;
            }
        }

        return null;
    }

    private function ended(ClassSession $session, array $object): void
    {
        $end = $this->time($object['end_time'] ?? null);
        $session->update(['status' => 'ended', 'ended_at' => $end]);
        // Anyone still "in" the meeting leaves when it ends.
        SessionParticipant::where('class_session_id', $session->id)->whereNull('left_at')->update(['left_at' => $end]);
        ComputeAttendance::dispatch($session)->afterResponse();
    }

    private function joined(ClassSession $session, array $p): void
    {
        SessionParticipant::create([
            'class_session_id' => $session->id,
            'user_id' => $this->userFor($p),
            'display_name' => mb_substr((string) ($p['user_name'] ?? ''), 0, 255),
            'zoom_participant_id' => (string) ($p['participant_user_id'] ?? $p['user_id'] ?? $p['id'] ?? ''),
            'joined_at' => $this->time($p['join_time'] ?? null),
        ]);
        if ($session->status === 'scheduled') {
            $session->update(['status' => 'live', 'started_at' => $session->started_at ?? now()]);
        }
    }

    private function left(ClassSession $session, array $p): void
    {
        $userId = $this->userFor($p);
        $zid = (string) ($p['participant_user_id'] ?? $p['user_id'] ?? $p['id'] ?? '');

        SessionParticipant::where('class_session_id', $session->id)->whereNull('left_at')
            ->where(fn ($q) => $userId ? $q->where('user_id', $userId) : $q->where('zoom_participant_id', $zid))
            ->latest('joined_at')->first()
            ?->update(['left_at' => $this->time($p['leave_time'] ?? null)]);
    }

    /** Our join sends customer_key = user id; the display name also ends with the Student ID as a fallback. */
    private function userFor(array $p): ?int
    {
        $key = (string) ($p['customer_key'] ?? '');
        if (ctype_digit($key) && User::whereKey((int) $key)->exists()) {
            return (int) $key;
        }
        if (preg_match('/LWE-STU-(\d{6})/', (string) ($p['user_name'] ?? ''), $m)) {
            return User::where('student_code', 'LWE-STU-'.$m[1])->value('id');
        }

        return null;
    }

    private function time(?string $value): CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse($value)->utc() : CarbonImmutable::now();
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }
}
