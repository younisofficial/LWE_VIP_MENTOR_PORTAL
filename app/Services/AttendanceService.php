<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Turns Zoom join/leave intervals into attendance:
 * minutes inside the class time (rejoins merged) → Present / Partial / Absent.
 * Manual marks by a mentor/admin are never overwritten.
 */
class AttendanceService
{
    public function compute(ClassSession $session): void
    {
        $classStart = $session->started_at ?? $session->starts_at;
        $classEnd = $session->ended_at ?? $session->endsAt();
        $length = max(1, $session->duration_minutes);

        $present = (int) Setting::get('attendance_present_percent');
        $partial = (int) Setting::get('attendance_partial_percent');

        $intervals = $session->participants()->whereNotNull('user_id')->get()->groupBy('user_id');

        foreach ($session->expectedStudents()->pluck('id') as $userId) {
            $existing = Attendance::where('class_session_id', $session->id)->where('user_id', $userId)->first();
            if ($existing?->source === 'manual') {
                continue;
            }

            $minutes = $this->minutesInside($intervals->get($userId, collect()), $classStart, $classEnd);
            $percent = $minutes / $length * 100;
            $status = $percent >= $present ? 'present' : ($percent >= $partial ? 'partial' : 'absent');

            Attendance::updateOrCreate(
                ['class_session_id' => $session->id, 'user_id' => $userId],
                ['minutes' => $minutes, 'status' => $status, 'source' => 'zoom'],
            );
        }
    }

    private function minutesInside($rows, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $ranges = $rows->map(function ($r) use ($from, $to) {
            $a = max($r->joined_at->timestamp, $from->timestamp);
            $b = min(($r->left_at ?? $to)->timestamp, $to->timestamp);

            return $b > $a ? [$a, $b] : null;
        })->filter()->sortBy(0)->values();

        // Merge overlapping ranges so rejoins are not counted twice.
        $total = 0;
        $current = null;
        foreach ($ranges as [$a, $b]) {
            if (! $current) {
                $current = [$a, $b];
            } elseif ($a <= $current[1]) {
                $current[1] = max($current[1], $b);
            } else {
                $total += $current[1] - $current[0];
                $current = [$a, $b];
            }
        }
        if ($current) {
            $total += $current[1] - $current[0];
        }

        return (int) round($total / 60);
    }
}
