<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Simple key/value settings editable by Super Admin. */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'max_devices_per_day' => 3,          // alert admins above this many distinct devices in 24h
        'admin_two_factor' => false,         // OTP after password for admins (off: login with phone/email + password only)
        'ai_enabled' => true,                // AI features on (still needs ANTHROPIC_API_KEY in .env)
        'ai_daily_limit' => 300,             // max AI requests per day for the whole portal (0 = no limit)
        'join_before_minutes' => 5,          // Join button opens this many minutes before class
        'attendance_present_percent' => 70,  // watched at least this % of the class = Present
        'attendance_partial_percent' => 30,  // at least this % = Partial, below = Absent
        'reminder_day' => true,              // WhatsApp + app reminder 1 day before
        'reminder_hour' => true,             // 1 hour before
        'reminder_start' => true,            // when the Join button opens
        'reminder_missed' => true,           // after class, to students who missed it
        'passport_start_from' => 0,          // new Skill Passport numbers start after this (and after any imported old ID)
        'weekly_report' => true,             // Monday 9 AM report email (with AI notes) to admins
        'auto_certificates' => true,         // issue certificates by themselves when a batch ends and rules are met
        'auto_nudges' => true,               // weekly WhatsApp/email nudge to students falling behind
        'campus_name' => 'Multan Campus',
        'campus_qr_seconds' => 60,           // the campus QR code changes this often
        'checkin_note_required' => true,     // self check-in asks "what did you learn today?"
        'email_enabled' => true,             // send emails at all (SMTP keys in .env)
        'email_students' => true,            // students: welcome, payment, enrollment, class emails
        'email_staff' => true,               // admins, super admins and mentors: new payments, enrollments, class emails
        'meta_pixel_id' => '',               // Meta (Facebook) pixel on public pages: mentors, passports
        'ga4_id' => '',                      // Google Analytics 4 measurement id (G-XXXX), optional
        'site_whatsapp' => '',               // enquiry number when a mentor has none
        'payment_instructions' => "Bank: [Bank name]\nAccount title: Learning with Earning\nAccount no: [number]\nJazzCash/Easypaisa: [number]",
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("setting:{$key}", fn () => static::where('key', $key)->value('value'));

        if ($value === null) {
            return $default ?? (self::DEFAULTS[$key] ?? null);
        }

        return json_decode($value, true) ?? $value;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        Cache::forget("setting:{$key}");
    }
}
