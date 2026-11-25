<?php

namespace App\Services\Ai;

use App\Models\User;

/** Reads the report numbers and writes what matters + what to do this week. */
class ReportAi
{
    private const SYSTEM = 'You are an operations analyst for Learning with Earning, a Pakistani skills academy (online + Multan campus). '
        .'You read report numbers and tell the owners what matters. Use ONLY the numbers given; never invent figures or causes you cannot see. '
        .'When a cause is a guess, say "possibly". Be short and practical. Currency is PKR.';

    public function __construct(private ClaudeClient $claude) {}

    /** @return array{headline:string, insights:array<string>, actions:array<string>} */
    public function insights(array $digest, string $language, ?User $user = null): array
    {
        $lang = match ($language) {
            'roman_urdu' => 'Write in simple Roman Urdu, keep names and numbers as they are.',
            'urdu' => 'Write in Urdu script, keep names and numbers as they are.',
            default => 'Write in simple English.',
        };

        $data = $this->claude->structured('report_insights', self::SYSTEM, [ClaudeClient::text(
            'Report numbers (JSON; *_change are % change vs the previous period, attendance_change is percentage points):'."\n"
            .json_encode($digest, JSON_UNESCAPED_UNICODE)."\n\n"
            .'Write: one headline sentence; 3-5 insights (each one line, with the number that shows it: good news, problems, which course/mentor stands out); '
            .'3 concrete actions for this week (who should do what). '.$lang
        )], 'save_insights', 'Save the report summary.', [
            'type' => 'object',
            'properties' => [
                'headline' => ['type' => 'string'],
                'insights' => ['type' => 'array', 'items' => ['type' => 'string']],
                'actions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['headline', 'insights', 'actions'],
        ], $user, 1500);

        $clean = fn ($list) => collect($list ?? [])->filter(fn ($x) => is_string($x) && trim($x) !== '')->map(fn ($x) => mb_substr(trim($x), 0, 400))->take(6)->values()->all();

        return [
            'headline' => mb_substr(trim((string) ($data['headline'] ?? '')), 0, 300),
            'insights' => $clean($data['insights'] ?? []),
            'actions' => $clean($data['actions'] ?? []),
        ];
    }
}
