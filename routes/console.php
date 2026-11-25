<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs. On the server, one cron line runs them all:
|   * * * * * cd /var/www/lwe-portal && php artisan schedule:run >> /dev/null 2>&1
| On your Windows PC (development), keep this running in a window:
|   php artisan schedule:work
*/
Schedule::command('lwe:class-reminders')->everyMinute()->withoutOverlapping();

// Module 4: results, certificates and nudges every morning (7 AM Pakistan), task-due reminders every hour.
Schedule::command('lwe:automation')->dailyAt('07:00')->timezone('Asia/Karachi')->withoutOverlapping();
Schedule::command('lwe:automation --tasks')->hourly()->withoutOverlapping();

// Module 5: weekly report to admins, Monday 9 AM Pakistan.
Schedule::command('lwe:weekly-report')->weeklyOn(1, '09:00')->timezone('Asia/Karachi');
