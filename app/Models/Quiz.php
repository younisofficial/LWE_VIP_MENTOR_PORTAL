<?php

namespace App\Models;

use App\Models\Concerns\ForEnrolledStudents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use ForEnrolledStudents;

    protected $fillable = [
        'course_id', 'lesson_id', 'batch_id', 'title', 'instructions', 'time_limit_minutes', 'pass_percent', 'max_attempts',
        'shuffle_questions', 'show_answers', 'due_at', 'status', 'published_at', 'created_by',
    ];

    protected $attributes = ['pass_percent' => 50, 'max_attempts' => 1, 'shuffle_questions' => true, 'show_answers' => true, 'status' => 'draft'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime', 'published_at' => 'datetime', 'shuffle_questions' => 'boolean', 'show_answers' => 'boolean',
            'pass_percent' => 'integer', 'max_attempts' => 'integer', 'time_limit_minutes' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isClosed(): bool
    {
        return $this->due_at !== null && now()->greaterThan($this->due_at);
    }
}
