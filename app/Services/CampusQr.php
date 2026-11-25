<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Rotating QR code for campus attendance. The code changes every N seconds,
 * so a photo of the QR sent on WhatsApp stops working almost at once.
 */
class CampusQr
{
    public function seconds(): int
    {
        return max(15, min(600, (int) Setting::get('campus_qr_seconds')));
    }

    private function window(?int $time = null): int
    {
        return intdiv($time ?? time(), $this->seconds());
    }

    private function tokenFor(int $window): string
    {
        return substr(hash_hmac('sha256', "lwe-campus|{$window}", (string) config('app.key')), 0, 20);
    }

    /** @return array{token:string, expires_in:int} */
    public function current(): array
    {
        $w = $this->window();

        return ['token' => $this->tokenFor($w), 'expires_in' => ($w + 1) * $this->seconds() - time()];
    }

    /** Valid for the current window and the one before (slow phones / slow scans). */
    public function isValid(?string $token): bool
    {
        if (! is_string($token) || strlen($token) !== 20) {
            return false;
        }
        $w = $this->window();

        return hash_equals($this->tokenFor($w), $token) || hash_equals($this->tokenFor($w - 1), $token);
    }
}
