<?php

namespace App\Jobs;

use App\Models\ClassSession;
use App\Services\Zoom\ZoomClient;
use App\Services\Zoom\ZoomException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Creates or updates the Zoom meeting of a class. Errors are saved on the class so admins see them. */
class SyncZoomMeeting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(public ClassSession $session, public string $action = 'create') {}

    public function handle(): void
    {
        $session = $this->session->fresh(['zoomAccount', 'course', 'batch']);
        if (! $session || $session->status === 'cancelled' || ! $session->zoomAccount) {
            return;
        }

        try {
            $client = ZoomClient::for($session->zoomAccount);
            ($this->action === 'update' && $session->zoom_meeting_id)
                ? $client->updateMeeting($session)
                : ($session->zoom_meeting_id ? $client->updateMeeting($session) : $client->createMeeting($session));
            $session->forceFill(['zoom_error' => null])->save();
        } catch (ZoomException $e) {
            $session->forceFill(['zoom_error' => mb_substr($e->getMessage(), 0, 500)])->save();
        }
    }
}
