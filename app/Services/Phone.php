<?php

namespace App\Services;

/**
 * Pakistani mobile numbers → one format: 923XXXXXXXXX (12 digits).
 * Accepts 03001234567, +92 300 1234567, 923001234567, 3001234567.
 */
class Phone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input);

        if (str_starts_with($digits, '0092')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '03') && strlen($digits) === 11) {
            $digits = '92'.substr($digits, 1);
        }
        if (str_starts_with($digits, '3') && strlen($digits) === 10) {
            $digits = '92'.$digits;
        }

        return preg_match('/^923\d{9}$/', $digits) ? $digits : null;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** 923001234567 → 0300-1234567 (for display). */
    public static function display(?string $normalized): ?string
    {
        if (! $normalized || strlen($normalized) !== 12) {
            return $normalized;
        }

        return '0'.substr($normalized, 2, 3).'-'.substr($normalized, 5);
    }

    /** 923001234567 → 0300-•••4567 (for places where the full number should not show). */
    public static function masked(?string $normalized): ?string
    {
        if (! $normalized || strlen($normalized) !== 12) {
            return $normalized;
        }

        return '0'.substr($normalized, 2, 3).'-•••'.substr($normalized, -4);
    }
}
