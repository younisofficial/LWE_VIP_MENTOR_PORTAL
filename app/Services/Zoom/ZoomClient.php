<?php

namespace App\Services\Zoom;

use App\Models\ClassSession;
use App\Models\ZoomAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Zoom REST API for one account, using a Server-to-Server OAuth app
 * (token: POST https://zoom.us/oauth/token?grant_type=account_credentials&account_id=…, valid ~1 hour).
 * Meetings are created for the account's host user (host_email); S2S apps should not use "me".
 */
class ZoomClient
{
    public function __construct(private ZoomAccount $account) {}

    public static function for(ZoomAccount $account): self
    {
        return new self($account);
    }

    public function token(bool $fresh = false): string
    {
        $key = 'zoom-token:'.$this->account->id;
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->account->client_id, $this->account->client_secret)
                ->timeout(20)
                ->post('https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($this->account->account_id));

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new ZoomException('Zoom login failed: '.($response->json('reason') ?? $response->json('error') ?? 'HTTP '.$response->status())
                    .'. Check Account ID, Client ID and Client Secret.');
            }

            return $response->json('access_token');
        });
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl('https://api.zoom.us/v2')->withToken($this->token())->acceptJson()->timeout(30);
    }

    private function check(Response $response, string $action): Response
    {
        if ($response->status() === 401) {
            Cache::forget('zoom-token:'.$this->account->id);
        }
        if (! $response->successful()) {
            throw new ZoomException("Zoom {$action} failed: ".($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return $response;
    }

    /** Checks the keys and the host user. Used by "Test connection". */
    public function test(): array
    {
        $user = $this->check($this->api()->get('/users/'.urlencode($this->account->host_email)), 'host check')->json();

        return ['host' => trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')) ?: $user['email'] ?? '', 'type' => $user['type'] ?? null];
    }

    /** Creates the Zoom meeting for a class and stores its number + passcode on the session. */
    public function createMeeting(ClassSession $session): void
    {
        $data = $this->check($this->api()->post('/users/'.urlencode($this->account->host_email).'/meetings', $this->payload($session)), 'create meeting')->json();

        $session->forceFill([
            'zoom_meeting_id' => (string) $data['id'],
            'zoom_password' => $data['password'] ?? null,
            'zoom_error' => null,
        ])->save();
    }

    public function updateMeeting(ClassSession $session): void
    {
        $this->check($this->api()->patch('/meetings/'.$session->zoom_meeting_id, $this->payload($session)), 'update meeting');
    }

    public function deleteMeeting(ClassSession $session): void
    {
        $response = $this->api()->delete('/meetings/'.$session->zoom_meeting_id);
        if ($response->status() !== 404) { // already gone is fine
            $this->check($response, 'delete meeting');
        }
    }

    /** Host start token (ZAK) so the mentor can start the class inside the portal. */
    public function zak(): string
    {
        return (string) $this->check($this->api()->get('/users/'.urlencode($this->account->host_email).'/token', ['type' => 'zak']), 'host token')->json('token');
    }

    /** Download a recording file (e.g. the transcript) with the token Zoom sends in the webhook. */
    public function download(string $url, ?string $downloadToken = null): string
    {
        $request = Http::timeout(60);
        $request = $downloadToken ? $request->withToken($downloadToken) : $request->withToken($this->token());

        return $this->check($request->get($url), 'download')->body();
    }

    private function payload(ClassSession $session): array
    {
        return [
            'topic' => mb_substr($session->title, 0, 190),
            'type' => 2, // scheduled meeting
            'start_time' => $session->starts_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration' => $session->duration_minutes,
            'timezone' => config('lwe.timezone'),
            'agenda' => mb_substr((string) ($session->course?->title.' · '.$session->batch?->name), 0, 1900),
            'settings' => [
                'join_before_host' => false,
                'waiting_room' => false,       // hundreds of students: no manual admitting
                'mute_upon_entry' => true,
                'participant_video' => false,
                'host_video' => true,
                'auto_recording' => 'cloud',   // recording + transcript feed the AI class notes
                'approval_type' => 2,          // no registration
                'meeting_authentication' => false,
            ],
        ];
    }
}
