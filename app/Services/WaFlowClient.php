<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp messages through WaFlow Hub.
 * The API shape (path, auth, field names, phone format, extra fields) comes from .env,
 * so it can be matched to WaFlow Hub without changing code. Admin → Settings has a "Send test WhatsApp" button.
 */
class WaFlowClient
{
    public function enabled(): bool
    {
        return (bool) config('lwe.waflow.enabled') && config('lwe.waflow.base_url') && config('lwe.waflow.token');
    }

    public function formatPhone(string $phone): string
    {
        $n = Phone::normalize($phone) ?? $phone; // 923001234567

        return match (config('lwe.waflow.phone_format')) {
            '+92' => '+'.$n,
            '0' => str_starts_with($n, '92') ? '0'.substr($n, 2) : $n,
            default => $n,
        };
    }

    /** @return array{ok:bool, status:?int, body:?string} */
    public function sendRaw(string $phone, string $message): array
    {
        $c = config('lwe.waflow');
        $payload = [$c['phone_field'] => $this->formatPhone($phone), $c['message_field'] => $message];
        $extra = json_decode((string) $c['extra'], true);
        if (is_array($extra)) {
            $payload = [...$extra, ...$payload];
        }

        $url = rtrim($c['base_url'], '/').'/'.ltrim($c['send_path'], '/');
        $req = Http::acceptJson()->timeout(15);
        $auth = (string) $c['auth'];
        if ($auth === 'bearer') {
            $req = $req->withToken($c['token']);
        } elseif (str_starts_with($auth, 'header:')) {
            $req = $req->withHeaders([substr($auth, 7) => $c['token']]);
        } elseif (str_starts_with($auth, 'query:')) {
            $url .= (str_contains($url, '?') ? '&' : '?').urlencode(substr($auth, 6)).'='.urlencode($c['token']);
        } elseif (str_starts_with($auth, 'body:')) {
            $payload[substr($auth, 5)] = $c['token'];
        }
        if ($c['as_form']) {
            $req = $req->asForm();
        }

        $response = $req->post($url, $payload);

        return ['ok' => $response->successful(), 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)];
    }

    /** @return bool true when the message was accepted (or logged in dev mode) */
    public function send(string $phone, string $message): bool
    {
        if (! $this->enabled()) {
            Log::channel('single')->info('[WhatsApp:dev] to '.$phone.': '.$message);

            return true;
        }

        try {
            $r = $this->sendRaw($phone, $message);
            if (! $r['ok']) {
                Log::warning('WaFlow send failed', ['phone' => $phone, 'status' => $r['status'], 'body' => $r['body']]);
            }

            return $r['ok'];
        } catch (\Throwable $e) {
            Log::error('WaFlow send error: '.$e->getMessage(), ['phone' => $phone]);

            return false;
        }
    }
}
