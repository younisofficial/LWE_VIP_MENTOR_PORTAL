<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** Audit log (who changed what) + basic settings. */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = Activity::with('causer:id,name')
            ->when($request->log, fn ($q, $l) => $q->where('log_name', $l))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'log' => $a->log_name,
                'event' => $a->event ?? $a->description,
                'subject' => class_basename((string) $a->subject_type).' #'.$a->subject_id,
                'by' => $a->causer?->name ?? 'System',
                'changes' => [...($a->attribute_changes?->toArray() ?? []), ...($a->properties?->toArray() ?? [])],
                'time' => $a->created_at->timezone(config('lwe.timezone'))->format('d M Y, g:i A'),
            ]);

        return Inertia::render('Admin/Activity/Index', [
            'logs' => $logs,
            'filters' => $request->only('log'),
            'logNames' => Activity::query()->distinct()->pluck('log_name'),
        ]);
    }

    public function settings(): Response
    {
        return Inertia::render('Admin/Activity/Settings', [
            'settings' => collect(array_keys(Setting::DEFAULTS))->mapWithKeys(fn ($k) => [$k => Setting::get($k)]),
            'mail' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'from' => config('mail.from.address'),
                'ready' => config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.password')),
            ],
            'ai' => [
                'key_set' => filled(config('lwe.ai.api_key')),
                'model' => config('lwe.ai.model'),
                'used_today' => \App\Models\AiLog::where('created_at', '>=', now()->startOfDay())->count(),
                'errors_today' => \App\Models\AiLog::where('created_at', '>=', now()->startOfDay())->where('status', 'error')->count(),
                'tokens_month' => (int) \App\Models\AiLog::where('created_at', '>=', now()->startOfMonth())->sum(\Illuminate\Support\Facades\DB::raw('input_tokens + output_tokens')),
                'by_feature' => \App\Models\AiLog::where('created_at', '>=', now()->startOfMonth())
                    ->selectRaw('feature, count(*) as c')->groupBy('feature')->pluck('c', 'feature'),
            ],
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_devices_per_day' => ['required', 'integer', 'between:1,20'],
            'admin_two_factor' => ['boolean'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'ai_enabled' => ['boolean'],
            'join_before_minutes' => ['required', 'integer', 'between:0,60'],
            'attendance_present_percent' => ['required', 'integer', 'between:1,100'],
            'attendance_partial_percent' => ['required', 'integer', 'between:0,100'],
            'reminder_day' => ['boolean'],
            'reminder_hour' => ['boolean'],
            'reminder_start' => ['boolean'],
            'reminder_missed' => ['boolean'],
            'ai_daily_limit' => ['required', 'integer', 'between:0,100000'],
            'auto_certificates' => ['boolean'],
            'weekly_report' => ['boolean'],
            'auto_nudges' => ['boolean'],
            'campus_name' => ['required', 'string', 'max:100'],
            'campus_qr_seconds' => ['required', 'integer', 'between:15,600'],
            'checkin_note_required' => ['boolean'],
            'email_enabled' => ['boolean'],
            'email_students' => ['boolean'],
            'email_staff' => ['boolean'],
            'meta_pixel_id' => ['nullable', 'regex:/^[0-9]{6,20}$/'],
            'ga4_id' => ['nullable', 'regex:/^G-[A-Z0-9]{4,16}$/'],
            'site_whatsapp' => ['nullable', 'string', 'max:20'],
        ], ['meta_pixel_id.regex' => 'The pixel ID is a number only, e.g. 1234567890123456.', 'ga4_id.regex' => 'It looks like G-ABC123XYZ.']);
        $data['meta_pixel_id'] = (string) ($data['meta_pixel_id'] ?? '');
        $data['ga4_id'] = (string) ($data['ga4_id'] ?? '');
        $data['site_whatsapp'] = $data['site_whatsapp'] ? (\App\Services\Phone::normalize($data['site_whatsapp']) ?? $data['site_whatsapp']) : '';

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }
        activity('settings')->causedBy($request->user())->withProperties($data)->log('settings.updated');

        return back()->with('success', 'Settings saved.');
    }

    /** Sends a test email to the logged-in Super Admin, and shows the real error if it fails. */
    public function testMail(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (blank($user->email)) {
            return back()->with('error', 'Add an email address to your profile first.');
        }

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Assalam o Alaikum {$user->name},\n\nThis is a test email from the LWE Learning Portal. If you can read this, email is working.\n\nLearning with Earning",
                fn ($m) => $m->to($user->email)->subject('Test email — '.config('app.name')),
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Email failed: '.$e->getMessage());
        }

        return back()->with('success', config('mail.default') === 'smtp'
            ? "Test email sent to {$user->email}. Check the inbox (and spam folder)."
            : 'Mail is in "'.config('mail.default').'" mode, so nothing was really sent. Put the SMTP keys in .env first.');
    }

    /** Sends a test WhatsApp to the logged-in admin and shows WaFlow Hub's real answer. */
    public function testWhatsapp(Request $request, \App\Services\WaFlowClient $wa): RedirectResponse
    {
        $user = $request->user();
        if (! $wa->enabled()) {
            return back()->with('error', 'WhatsApp is off. Set WAFLOW_ENABLED=true, WAFLOW_BASE_URL and WAFLOW_API_TOKEN in .env, then run php artisan config:clear.');
        }
        try {
            $r = $wa->sendRaw($user->phone, "Assalam o Alaikum {$user->name}! Test message from the LWE Learning Portal ✔");
        } catch (\Throwable $e) {
            return back()->with('error', 'WhatsApp failed: '.$e->getMessage());
        }

        return back()->with($r['ok'] ? 'success' : 'error', ($r['ok'] ? 'Sent to ' : 'WaFlow Hub refused it ('.$r['status'].'): ').($r['ok'] ? $wa->formatPhone($user->phone) : $r['body']));
    }
}
