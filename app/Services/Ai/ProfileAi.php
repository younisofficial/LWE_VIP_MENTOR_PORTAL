<?php

namespace App\Services\Ai;

use App\Models\User;

/** AI for public pages: student passport summary, mentor bio and success stories. */
class ProfileAi
{
    private const SYSTEM = 'You write short, honest professional profile text for Learning with Earning (Pakistan). '
        .'Use only the facts given. Never invent numbers, clients, employers, awards or earnings. Plain text, no hashtags, no emojis.';

    public function __construct(private ClaudeClient $claude) {}

    private function lang(string $language): string
    {
        return match ($language) {
            'roman_urdu' => 'Write in simple Roman Urdu, keep skill names in English.',
            'urdu' => 'Write in Urdu script, keep skill names in English.',
            default => 'Write in clear, simple English.',
        };
    }

    /** 2-3 sentence summary for a student's Skill Passport, from their real record. */
    public function passportSummary(array $passport, string $language, User $user): string
    {
        $facts = json_encode([
            'name' => $passport['student']['name'],
            'verified_skills' => $passport['verified_skills'],
            'skills_in_progress' => $passport['learning_skills'],
            'courses' => collect($passport['courses'])->map(fn ($c) => ['course' => $c['title'], 'status' => $c['status'], 'score' => $c['score'], 'certificate' => $c['certificate']['grade'] ?? null]),
            'activity' => $passport['stats'],
            'badges' => collect($passport['badges'])->where('on', true)->pluck('title'),
        ], JSON_UNESCAPED_UNICODE);

        $data = $this->claude->structured('passport_summary', self::SYSTEM, [ClaudeClient::text(
            "Student record (facts):\n{$facts}\n\nWrite a 2-3 sentence profile summary for this student's public Skill Passport, "
            .'in third person, that a client or employer would read. Mention verified skills first and real effort signals '
            .'(attendance, tasks, streak) only if the numbers are good. '.$this->lang($language)
        )], 'save_summary', 'Save the summary.', [
            'type' => 'object', 'properties' => ['summary' => ['type' => 'string']], 'required' => ['summary'],
        ], $user, 600);

        return mb_substr(trim((string) ($data['summary'] ?? '')), 0, 800);
    }

    /** Mentor landing page text from rough notes. */
    public function mentorPage(string $name, string $notes, string $language, User $user): array
    {
        $data = $this->claude->structured('mentor_page', self::SYSTEM, [ClaudeClient::text(
            "Mentor: {$name}\nNotes from the academy (facts only):\n{$notes}\n\n"
            .'Write a mentor landing page: a one-line headline, a warm 2-paragraph bio, 3-6 short expertise tags, '
            .'and up to 4 highlight numbers ONLY if the notes give them (value + short label). '.$this->lang($language)
        )], 'save_mentor_page', 'Save the mentor page text.', [
            'type' => 'object',
            'properties' => [
                'headline' => ['type' => 'string'],
                'bio' => ['type' => 'string'],
                'expertise' => ['type' => 'array', 'items' => ['type' => 'string']],
                'highlights' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['value' => ['type' => 'string'], 'label' => ['type' => 'string']], 'required' => ['value', 'label']]],
            ],
            'required' => ['headline', 'bio', 'expertise', 'highlights'],
        ], $user, 1500);

        return [
            'headline' => mb_substr(trim((string) ($data['headline'] ?? '')), 0, 200),
            'bio' => mb_substr(trim((string) ($data['bio'] ?? '')), 0, 5000),
            'expertise' => collect($data['expertise'] ?? [])->filter(fn ($x) => is_string($x) && trim($x) !== '')->map(fn ($x) => mb_substr(trim($x), 0, 40))->take(8)->values()->all(),
            'highlights' => collect($data['highlights'] ?? [])->filter(fn ($h) => is_array($h) && filled($h['value'] ?? null))
                ->map(fn ($h) => ['value' => mb_substr((string) $h['value'], 0, 20), 'label' => mb_substr((string) ($h['label'] ?? ''), 0, 40)])->take(4)->values()->all(),
        ];
    }

    /** Turns a rough student story into a clean testimonial-style story (facts kept as given). */
    public function polishStory(string $rough, string $language, User $user): array
    {
        $data = $this->claude->structured('success_story', self::SYSTEM, [ClaudeClient::text(
            "Rough success story from the academy:\n{$rough}\n\nRewrite it as a short success story (3-4 sentences) in third person, "
            .'plus a one-line result. Keep every fact exactly as given; add nothing new. '.$this->lang($language)
        )], 'save_story', 'Save the story.', [
            'type' => 'object',
            'properties' => ['story' => ['type' => 'string'], 'result' => ['type' => 'string']],
            'required' => ['story', 'result'],
        ], $user, 800);

        return ['story' => mb_substr(trim((string) ($data['story'] ?? '')), 0, 2000), 'result' => mb_substr(trim((string) ($data['result'] ?? '')), 0, 200)];
    }
}
