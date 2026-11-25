<?php

namespace App\Services\Ai;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\QaQuestion;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Module 3 AI: quiz maker, task writer, submission checker and Q&A answer drafts.
 * Everything is a draft or a suggestion — a mentor/admin confirms before students see it.
 */
class EngagementAi
{
    private const SYSTEM = 'You are a teaching assistant at Learning with Earning (Pakistan), helping mentors of practical digital-skills courses '
        .'(e-commerce, freelancing, design, marketing). Students are often beginners learning on mobile. '
        .'Be accurate, practical and kind. Never invent facts, prices or statistics; if unsure, say so.';

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private ClaudeClient $claude) {}

    private function languageLine(string $language): string
    {
        return match ($language) {
            'roman_urdu' => 'Write in simple Roman Urdu (Urdu in English letters), keep technical terms in English.',
            'urdu' => 'Write in Urdu script, keep technical terms in English.',
            default => 'Write in simple English.',
        };
    }

    private function lessonText(?Lesson $lesson): string
    {
        if (! $lesson) {
            return '';
        }

        return "Lesson: {$lesson->title}\n"
            .($lesson->summary ? "Summary: {$lesson->summary}\n" : '')
            .($lesson->body ? 'Lesson notes: '.mb_substr(strip_tags($lesson->body), 0, 8000)."\n" : '');
    }

    /** Quiz questions from a lesson and/or a topic. */
    public function quiz(Course $course, ?Lesson $lesson, ?string $topic, int $count, string $difficulty, string $language, User $user): array
    {
        $count = max(1, min(25, $count));
        $prompt = "Course: {$course->title}\n".$this->lessonText($lesson)
            .($topic ? "Topic / mentor notes: {$topic}\n" : '')
            ."Difficulty: {$difficulty}\n"
            ."Write {$count} quiz questions that check real understanding (not trick questions). "
            .'Mostly "single" (one right answer, 4 options); some "true_false" (options exactly ["True","False"]); '
            .'at most a few "multiple" (two or more right answers). "correct" holds 0-based option indexes. '
            .'Add a one-line explanation for each. '.$this->languageLine($language);

        $data = $this->claude->structured('quiz_maker', self::SYSTEM, [ClaudeClient::text($prompt)], 'save_quiz',
            'Save the quiz questions.', [
                'type' => 'object',
                'properties' => [
                    'questions' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['single', 'multiple', 'true_false']],
                            'question' => ['type' => 'string'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'correct' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'explanation' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'question', 'options', 'correct'],
                    ]],
                ],
                'required' => ['questions'],
            ], $user, 6000);

        return collect($data['questions'] ?? [])
            ->map(fn ($q) => self::cleanQuestion(is_array($q) ? $q : []))
            ->filter()
            ->take(25)
            ->values()
            ->all();
    }

    /** Makes an AI (or pasted) question safe to save; null when unusable. */
    public static function cleanQuestion(array $q): ?array
    {
        $type = in_array($q['type'] ?? '', ['single', 'multiple', 'true_false'], true) ? $q['type'] : 'single';
        $options = collect($q['options'] ?? [])->filter(fn ($o) => is_string($o) && trim($o) !== '')
            ->map(fn ($o) => mb_substr(trim($o), 0, 500))->take(8)->values()->all();
        if ($type === 'true_false') {
            $options = ['True', 'False'];
        }
        $correct = collect($q['correct'] ?? [])->map(fn ($i) => (int) $i)
            ->filter(fn ($i) => $i >= 0 && $i < count($options))->unique()->values()->all();
        if ($type !== 'multiple') {
            $correct = array_slice($correct, 0, 1);
        }
        $text = trim((string) ($q['question'] ?? ''));
        if ($text === '' || count($options) < 2 || $correct === []) {
            return null;
        }

        return [
            'type' => $type,
            'question' => mb_substr($text, 0, 2000),
            'options' => $options,
            'correct' => $correct,
            'explanation' => mb_substr(trim((string) ($q['explanation'] ?? '')), 0, 1000),
            'marks' => max(1, min(10, (int) ($q['marks'] ?? 1))),
        ];
    }

    /** Title, instructions, rubric and marks for a practical task. */
    public function taskDraft(Course $course, ?Lesson $lesson, ?string $topic, string $language, User $user): array
    {
        $prompt = "Course: {$course->title}\n".$this->lessonText($lesson)
            .($topic ? "Mentor notes: {$topic}\n" : '')
            .'Write one practical task (homework) students can finish in 1-3 hours on a phone or laptop, '
            .'that proves they can apply this lesson. Give clear numbered steps and say exactly what to submit '
            .'(screenshot, link or short text). Also write a short marking rubric for the mentor. '.$this->languageLine($language);

        $data = $this->claude->structured('task_writer', self::SYSTEM, [ClaudeClient::text($prompt)], 'save_task',
            'Save the task.', [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Short task title, under 80 characters'],
                    'instructions' => ['type' => 'string'],
                    'rubric' => ['type' => 'string', 'description' => '3-5 lines: what earns full marks, what loses marks'],
                    'submission_type' => ['type' => 'string', 'enum' => ['any', 'text', 'file', 'link']],
                    'max_marks' => ['type' => 'integer'],
                ],
                'required' => ['title', 'instructions', 'rubric', 'submission_type', 'max_marks'],
            ], $user, 2500);

        return [
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 200),
            'instructions' => mb_substr((string) ($data['instructions'] ?? ''), 0, 20000),
            'rubric' => mb_substr((string) ($data['rubric'] ?? ''), 0, 5000),
            'submission_type' => in_array($data['submission_type'] ?? '', Task::SUBMISSION_TYPES, true) ? $data['submission_type'] : 'any',
            'max_marks' => max(1, min(100, (int) ($data['max_marks'] ?? 10))),
        ];
    }

    /** Suggested marks + feedback for one submission. Reads an uploaded image too. */
    public function reviewSubmission(TaskSubmission $submission, string $language, User $user): array
    {
        $task = $submission->task;
        $prompt = "Task: {$task->title}\nInstructions:\n".mb_substr($task->instructions, 0, 6000)."\n"
            .($task->rubric ? "Rubric:\n{$task->rubric}\n" : '')
            ."Maximum marks: {$task->max_marks}\n\nStudent's submission:\n"
            .($submission->body ? 'Text: '.mb_substr($submission->body, 0, 8000)."\n" : '')
            .($submission->link ? "Link (you cannot open it; judge only what is described): {$submission->link}\n" : '')
            .($submission->file_name ? "Uploaded file: {$submission->file_name}\n" : '')
            ."\nSuggest fair marks and write feedback the student will read: what is good, what to fix, one next step. "
            .'If the submission is empty, off-topic or copied text, give low marks and say why politely. '
            .$this->languageLine($language);

        $content = [ClaudeClient::text($prompt)];
        if ($submission->file_path && Storage::disk('local')->exists($submission->file_path)) {
            $mime = Storage::disk('local')->mimeType($submission->file_path);
            if (in_array($mime, self::IMAGE_TYPES, true) && Storage::disk('local')->size($submission->file_path) < 4_500_000) {
                array_unshift($content, ClaudeClient::image(Storage::disk('local')->get($submission->file_path), $mime));
            }
        }

        $data = $this->claude->structured('task_checker', self::SYSTEM, $content, 'save_review',
            'Save the suggested marks and feedback.', [
                'type' => 'object',
                'properties' => [
                    'marks' => ['type' => 'number'],
                    'feedback' => ['type' => 'string'],
                ],
                'required' => ['marks', 'feedback'],
            ], $user, 1500);

        return [
            'marks' => round(max(0, min((float) $task->max_marks, (float) ($data['marks'] ?? 0))), 1),
            'feedback' => mb_substr(trim((string) ($data['feedback'] ?? '')), 0, 5000),
        ];
    }

    /** Draft answer for a Q&A question (mentor edits and posts it). Reads attached screenshots. */
    public function answerDraft(QaQuestion $question, string $language, User $user): string
    {
        $prompt = "Course: {$question->course->title}\n"
            .($question->lesson ? "Lesson: {$question->lesson->title}\n" : '')
            ."Student question: {$question->title}\n"
            .($question->body ? 'Details: '.mb_substr($question->body, 0, 6000)."\n" : '')
            ."\nWrite a helpful answer a mentor can post: direct answer first, then short steps. "
            .'If a screenshot shows an error, explain the likely cause and fix. If you are not sure, say what to check. '
            .$this->languageLine($language);

        $content = [];
        foreach (array_slice($question->images ?? [], 0, 3) as $path) {
            if (Storage::disk('local')->exists($path)) {
                $mime = Storage::disk('local')->mimeType($path);
                if (in_array($mime, self::IMAGE_TYPES, true) && Storage::disk('local')->size($path) < 4_500_000) {
                    $content[] = ClaudeClient::image(Storage::disk('local')->get($path), $mime);
                }
            }
        }
        $content[] = ClaudeClient::text($prompt);

        $data = $this->claude->structured('qa_answer', self::SYSTEM, $content, 'save_answer',
            'Save the draft answer.', [
                'type' => 'object',
                'properties' => ['answer' => ['type' => 'string']],
                'required' => ['answer'],
            ], $user, 2000);

        return mb_substr(trim((string) ($data['answer'] ?? '')), 0, 10000);
    }
}
