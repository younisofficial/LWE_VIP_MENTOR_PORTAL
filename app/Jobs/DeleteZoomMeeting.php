<?php

namespace App\Jobs;

use App\Models\ClassSession;
use App\Models\ZoomAccount;
use App\Services\Zoom\ZoomClient;
use App\Services\Zoom\ZoomException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class DeleteZoomMeeting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $zoomAccountId, public string $meetingId) {}

    public function handle(): void
    {
        $account = ZoomAccount::find($this->zoomAccountId);
        if (! $account) {
            return;
        }
        try {
            ZoomClient::for($account)->deleteMeeting(new ClassSession(['zoom_meeting_id' => $this->meetingId]));
        } catch (ZoomException $e) {
            Log::warning('Zoom delete failed: '.$e->getMessage(), ['meeting' => $this->meetingId]);
        }
    }
}
