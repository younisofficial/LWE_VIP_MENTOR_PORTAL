<?php

namespace App\Services;

use App\Models\LoginLog;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SuspiciousDevicesAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Login device rules: trusted-device lock, login history, "too many devices" alert,
 * and the single active session (users.current_session_id).
 *
 * Note: the device "fingerprint" is a hash of the browser's user agent. It is a deterrent,
 * not strong proof of a device — the mobile/desktop apps in Module 5 will send a real device ID.
 */
class DeviceGuard
{
    public const SESSION_KEY = 'lwe_device_token';

    public function hashFor(Request $request): string
    {
        return hash('sha256', (string) $request->userAgent());
    }

    public function labelFor(Request $request): string
    {
        $ua = (string) $request->userAgent();
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        return "{$browser} on {$os}";
    }

    /** Is this device allowed for the user (trusted-device lock)? */
    public function allows(User $user, Request $request): bool
    {
        return ! $user->locked_device_hash || hash_equals($user->locked_device_hash, $this->hashFor($request));
    }

    public function log(User $user, Request $request, string $result = 'success'): void
    {
        LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'device_hash' => $this->hashFor($request),
            'device_label' => $this->labelFor($request),
            'result' => $result,
        ]);
    }

    /** Called after a successful login + session regenerate. */
    public function startSession(User $user, Request $request): void
    {
        // A random token kept in this browser's session. Logging in elsewhere replaces it,
        // so this browser no longer matches and is logged out (EnsureSingleSession).
        $token = Str::random(40);
        $request->session()->put(self::SESSION_KEY, $token);

        $user->forceFill([
            'current_session_id' => $token,
            'last_login_at' => now(),
        ])->save();

        $this->log($user, $request);
        $this->checkTooManyDevices($user);
    }

    private function checkTooManyDevices(User $user): void
    {
        $limit = (int) Setting::get('max_devices_per_day');
        $devices = LoginLog::where('user_id', $user->id)
            ->where('result', 'success')
            ->where('created_at', '>=', now()->subDay())
            ->distinct()
            ->count('device_hash');

        if ($devices > $limit && (! $user->flagged_at || $user->flagged_at->lt(now()->subDay()))) {
            $user->forceFill(['flagged_at' => now()])->save();
            $admins = User::role([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])->get();
            Notification::send($admins, new SuspiciousDevicesAlert($user, $devices));
        }
    }
}
