<?php

namespace App\Services\Ai;

use App\Models\AiLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Anthropic Messages API.
 *
 * Every feature asks Claude to answer by "calling" one tool whose input schema is the JSON we want back,
 * so we always get structured data (no fragile text parsing). Calls are logged and limited per day.
 */
class ClaudeClient
{
    public function isReady(): bool
    {
        return (bool) Setting::get('ai_enabled') && filled(config('lwe.ai.api_key'));
    }

    public function usedToday(): int
    {
        return AiLog::where('created_at', '>=', now()->startOfDay())->count();
    }

    /**
     * @param  array  $content  user message content blocks (text and/or image)
     * @param  array  $schema  JSON schema of the answer
     * @return array the structured answer
     */
    public function structured(string $feature, string $system, array $content, string $toolName, string $toolDescription, array $schema, ?User $user = null, int $maxTokens = 2000): array
    {
        if (! Setting::get('ai_enabled')) {
            throw new AiUnavailable('AI is switched off. Turn it on in Settings.');
        }
        if (blank(config('lwe.ai.api_key'))) {
            throw new AiUnavailable('AI key missing. Add ANTHROPIC_API_KEY to the .env file.');
        }
        $limit = (int) Setting::get('ai_daily_limit');
        if ($limit > 0 && $this->usedToday() >= $limit) {
            throw new AiUnavailable("Today's AI limit ({$limit} requests) is used up. Raise it in Settings or try tomorrow.");
        }

        $model = config('lwe.ai.model');

        try {
            $response = Http::baseUrl(config('lwe.ai.base_url'))
                ->withHeaders([
                    'x-api-key' => config('lwe.ai.api_key'),
                    'anthropic-version' => config('lwe.ai.version'),
                ])
                ->acceptJson()
                ->timeout(config('lwe.ai.timeout'))
                ->post('/v1/messages', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'tools' => [[
                        'name' => $toolName,
                        'description' => $toolDescription,
                        'input_schema' => $schema,
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => $toolName],
                    'messages' => [['role' => 'user', 'content' => $content]],
                ]);
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
            $this->log($feature, $model, $user, 0, 0, 'error', $reason);
            Log::error('Claude API connection failed', ['feature' => $feature, 'error' => $reason]);

            // Show admins the real reason, with a hint for the common Windows/XAMPP certificate problem.
            $hint = str_contains($reason, 'cURL error 60') || str_contains(strtolower($reason), 'ssl certificate')
                ? ' Fix: PHP needs a certificate file (cacert.pem). See INSTALL guide, "AI not reaching".'
                : (str_contains($reason, 'cURL error 28') ? ' The request timed out; check the internet connection.' : '');

            throw new AiUnavailable('Could not reach the AI service: '.mb_substr($reason, 0, 180).$hint);
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? ('HTTP '.$response->status());
            $this->log($feature, $model, $user, 0, 0, 'error', $message);
            Log::warning('Claude API error', ['feature' => $feature, 'status' => $response->status(), 'message' => $message]);
            throw new AiUnavailable('AI request failed: '.$message);
        }

        $usage = $response->json('usage') ?? [];
        $toolUse = collect($response->json('content') ?? [])->firstWhere('type', 'tool_use');

        if (! $toolUse || ! is_array($toolUse['input'] ?? null)) {
            $this->log($feature, $model, $user, $usage['input_tokens'] ?? 0, $usage['output_tokens'] ?? 0, 'error', 'No structured answer');
            throw new AiUnavailable('AI did not return an answer. Please try again.');
        }

        $this->log($feature, $model, $user, $usage['input_tokens'] ?? 0, $usage['output_tokens'] ?? 0);

        return $toolUse['input'];
    }

    public static function text(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    public static function image(string $binary, string $mediaType): array
    {
        return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($binary)]];
    }

    private function log(string $feature, string $model, ?User $user, int $in, int $out, string $status = 'ok', ?string $error = null): void
    {
        AiLog::create([
            'user_id' => $user?->id,
            'feature' => $feature,
            'model' => $model,
            'input_tokens' => $in,
            'output_tokens' => $out,
            'status' => $status,
            'error' => $error ? mb_substr($error, 0, 500) : null,
        ]);
    }
}
