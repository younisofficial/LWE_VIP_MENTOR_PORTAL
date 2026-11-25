<?php

namespace App\Services\Zoom;

/**
 * Meeting SDK signature (a JWT signed with the SDK secret, HS256).
 * role 0 = attendee, 1 = host. Since Meeting SDK v5 the payload must contain appKey.
 */
class ZoomSignature
{
    public static function make(string $sdkKey, string $sdkSecret, string $meetingNumber, int $role, int $ttlSeconds = 7200): string
    {
        $iat = time() - 30; // small clock-skew allowance
        $exp = $iat + max(1800, $ttlSeconds);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'appKey' => $sdkKey,
            'sdkKey' => $sdkKey,
            'mn' => preg_replace('/\D/', '', $meetingNumber),
            'role' => $role,
            'iat' => $iat,
            'exp' => $exp,
            'tokenExp' => $exp,
        ];

        $segments = [self::b64(json_encode($header)), self::b64(json_encode($payload))];
        $segments[] = self::b64(hash_hmac('sha256', implode('.', $segments), $sdkSecret, true));

        return implode('.', $segments);
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
