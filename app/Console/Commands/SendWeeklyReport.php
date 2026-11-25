<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\WeeklyReport;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\ReportAi;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/** Monday 9 AM (Pakistan): last 7 days' numbers + AI notes to every admin and super admin. */
class SendWeeklyReport extends Command
{
    protected $signature = 'lwe:weekly-report';

    protected $description = 'Email the weekly report to admins';

    public function handle(ReportService $reports, ReportAi $ai): int
    {
        if (! Setting::get('weekly_report')) {
            $this->line('Weekly report is switched off in Settings.');

            return self::SUCCESS;
        }
        [$from, $to, $label] = $reports->range('7d');
        $period = $from->setTimezone(config('lwe.timezone'))->format('d M').' – '.$to->setTimezone(config('lwe.timezone'))->format('d M Y');
        $digest = $reports->digest($from, $to, $label);

        try {
            $notes = $ai->insights($digest, 'roman_urdu');
        } catch (AiUnavailable $e) {
            $notes = null; // the numbers still go out
        }

        $admins = User::role([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])->get();
        Notification::send($admins, new WeeklyReport($digest['kpis'], $notes, $period));
        $this->info("Weekly report sent to {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}
