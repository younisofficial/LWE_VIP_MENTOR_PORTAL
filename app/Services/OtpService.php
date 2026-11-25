<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function __construct(private WaFlowClient $whatsapp) {}

    /**
     * Create a fresh code (old unused codes for the same purpose are voided) and send it.
     * Returns the channel used: 'whatsapp', 'email' or 'log'.
     */
    public function issue(User $user, string $purpose): string
    {
        OtpCode::where('user_id', $user->id)->where('purpose', $purpose)->whereNull('used_at')->delete();

        $length = config('lwe.otp.length');
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        OtpCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('lwe.otp.ttl_minutes')),
        ]);

        $text = "Learning with Earning: aap ka code {$code} hai. Ye ".config('lwe.otp.ttl_minutes').' minute mein expire ho jaye ga. Kisi ko na batayein.';

        // 1) WhatsApp via WaFlow Hub (when configured)
        if ($user->phone && $this->whatsapp->enabled() && $this->whatsapp->send($user->phone, $text)) {
            return 'whatsapp';
        }

        // 2) Email (with MAIL_MAILER=log the code lands in storage/logs/laravel.log during development)
        if ($user->email) {
            Mail::raw($text, fn ($m) => $m->to($user->email)->subject('Your Learning with Earning code'));

            return 'email';
        }

        Log::info("[OTP:dev] user {$user->id} {$purpose}: {$code}");

        return 'log';
    }

    /** true when the code matches; the code is then marked used. */
    public function verify(User $user, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= config('lwe.otp.max_attempts')) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->forceFill(['used_at' => now()])->save();

        return true;
    }
}
