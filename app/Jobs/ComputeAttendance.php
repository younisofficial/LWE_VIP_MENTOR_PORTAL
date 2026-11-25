<?php

namespace App\Jobs;

use App\Models\ClassSession;
use App\Services\AttendanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeAttendance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ClassSession $session) {}

    public function handle(AttendanceService $attendance): void
    {
        $attendance->compute($this->session->fresh());
    }
}
