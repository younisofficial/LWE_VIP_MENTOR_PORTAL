<?php

namespace App\Services\Ai;

use App\Models\Course;
use App\Models\User;

/** AI Course Builder + AI Lesson Writer. Output is a draft: the admin edits it before saving. */
class CourseAi
{
    private const SYSTEM = 'You help Learning with Earning (Pakistan) design practical digital-skills courses for Pakistani students '
        .'(many are beginners, learning on mobile, aiming to earn online). Be specific, practical and honest: no fake statistics, '
        .'no earnings promises, no invented testimonials. Keep sentences short and simple.';

    public function __construct(private ClaudeClient $claude) {}

    private function languageLine(string $language): string
    {
        return match ($language) {
            'roman_urdu' => 'Write all text in simple Roman Urdu (Urdu written in English letters), keeping technical terms in English.',
            'urdu' => 'Write all text in Urdu script, keeping technical terms in English.',
            default => 'Write all text in simple English.',
        };
    }

    /**
     * Everything the course wizard can fill: card text, category, level, description,
     * learning points, requirements, FAQs and skills. Works before the course is saved.
     */
    public function draft(string $title, ?string $level, ?string $shortDescription, ?string $notes, string $language, User $user, array $categories = []): array
    {
        $prompt = "Course title: {$title}\n"
            .($level ? "Level: {$level}\n" : '')
            .($shortDescription ? "Short description: {$shortDescription}\n" : '')
            .($categories ? 'Existing categories (reuse one if it fits): '.implode(', ', $categories)."\n" : '')
            .($notes ? "Admin notes about the course:\n{$notes}\n" : '')
            ."\n".$this->languageLine($language)
            ."\nWrite the course page content.";

        $data = $this->claude->structured('course_content', self::SYSTEM, [ClaudeClient::text($prompt)], 'save_course_content',
            'Save the course page content.', [
                'type' => 'object',
                'properties' => [
                    'short_description' => ['type' => 'string', 'description' => 'One line for the course card, max 200 characters'],
                    'category' => ['type' => 'string', 'description' => 'Short category in English, e.g. "E-commerce", "Freelancing", "Graphic Design"'],
                    'level' => ['type' => 'string', 'enum' => ['beginner', 'intermediate', 'advanced']],
                    'description' => ['type' => 'string', 'description' => '2-4 short paragraphs'],
                    'what_you_learn' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '5-8 concrete outcomes, each one short line under 120 characters'],
                    'requirements' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '2-4 items, each under 120 characters'],
                    'faqs' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => ['q' => ['type' => 'string'], 'a' => ['type' => 'string']],
                        'required' => ['q', 'a'],
                    ], 'description' => '4-6 questions students really ask'],
                    'skills' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '3-6 short skill names in English, e.g. "Product Research"'],
                ],
                'required' => ['short_description', 'category', 'level', 'description', 'what_you_learn', 'requirements', 'faqs', 'skills'],
            ], $user, 3000);

        return [
            'short_description' => mb_substr((string) ($data['short_description'] ?? ''), 0, 300),
            'category' => mb_substr((string) ($data['category'] ?? ''), 0, 80),
            'level' => in_array($data['level'] ?? '', ['beginner', 'intermediate', 'advanced'], true) ? $data['level'] : ($level ?: 'beginner'),
            'description' => (string) ($data['description'] ?? ''),
            'what_you_learn' => array_map(fn ($x) => mb_substr(trim($x), 0, 500), array_values(array_slice(array_filter((array) ($data['what_you_learn'] ?? []), 'is_string'), 0, 20))),
            'requirements' => array_map(fn ($x) => mb_substr(trim($x), 0, 500), array_values(array_slice(array_filter((array) ($data['requirements'] ?? []), 'is_string'), 0, 20))),
            'faqs' => collect($data['faqs'] ?? [])->filter(fn ($f) => is_array($f) && filled($f['q'] ?? null))
                ->map(fn ($f) => ['q' => mb_substr((string) $f['q'], 0, 500), 'a' => mb_substr((string) ($f['a'] ?? ''), 0, 3000)])->take(20)->values()->all(),
            'skills' => collect($data['skills'] ?? [])->filter(fn ($s) => is_string($s) && trim($s) !== '')
                ->map(fn ($s) => mb_substr(trim($s), 0, 60))->unique()->take(15)->values()->all(),
        ];
    }

    /** Modules + lessons outline. */
    public function outline(Course $course, ?string $notes, int $weeks, string $language, User $user): array
    {
        $prompt = "Course title: {$course->title}\nLevel: {$course->level}\n"
            .($course->description ? 'Course description: '.mb_substr($course->description, 0, 3000)."\n" : '')
            .($notes ? "Admin notes:\n{$notes}\n" : '')
            ."Length: about {$weeks} weeks, 2-3 classes per week.\n"
            .$this->languageLine($language)
            ."\nPlan the curriculum as modules (one per week or topic) with lessons. Mix live classes, recorded videos, reading notes and practical tasks. "
            .'End each module with a practical task.';

        $data = $this->claude->structured('course_outline', self::SYSTEM, [ClaudeClient::text($prompt)], 'save_outline',
            'Save the course outline.', [
                'type' => 'object',
                'properties' => [
                    'modules' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'lessons' => ['type' => 'array', 'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'type' => ['type' => 'string', 'enum' => ['live_class', 'recorded_video', 'reading', 'task', 'quiz']],
                                    'summary' => ['type' => 'string'],
                                    'duration_minutes' => ['type' => 'integer'],
                                ],
                                'required' => ['title', 'type', 'summary'],
                            ]],
                        ],
                        'required' => ['title', 'lessons'],
                    ]],
                ],
                'required' => ['modules'],
            ], $user, 4000);

        return collect($data['modules'] ?? [])->filter(fn ($m) => is_array($m) && filled($m['title'] ?? null))->take(20)
            ->map(fn ($m) => [
                'title' => mb_substr((string) $m['title'], 0, 150),
                'lessons' => collect($m['lessons'] ?? [])->filter(fn ($l) => is_array($l) && filled($l['title'] ?? null))->take(15)
                    ->map(fn ($l) => [
                        'title' => mb_substr((string) $l['title'], 0, 200),
                        'type' => in_array($l['type'] ?? '', \App\Models\Lesson::TYPES, true) ? $l['type'] : 'reading',
                        'summary' => mb_substr((string) ($l['summary'] ?? ''), 0, 1000),
                        'duration_minutes' => isset($l['duration_minutes']) ? max(1, min(600, (int) $l['duration_minutes'])) : null,
                    ])->values()->all(),
            ])->values()->all();
    }

    /** Notes for one lesson. */
    public function lesson(Course $course, string $title, string $type, ?string $hint, string $language, User $user): array
    {
        $prompt = "Course: {$course->title}\nLesson title: {$title}\nLesson type: {$type}\n"
            .($hint ? "Extra instructions: {$hint}\n" : '')
            .$this->languageLine($language)
            ."\nWrite a one-line summary and the lesson notes students read (steps, key points, a short practice exercise). Plain text, use '-' for bullets.";

        $data = $this->claude->structured('lesson_writer', self::SYSTEM, [ClaudeClient::text($prompt)], 'save_lesson',
            'Save the lesson text.', [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                ],
                'required' => ['summary', 'body'],
            ], $user, 3000);

        return [
            'summary' => mb_substr((string) ($data['summary'] ?? ''), 0, 1000),
            'body' => mb_substr((string) ($data['body'] ?? ''), 0, 50000),
        ];
    }
}
