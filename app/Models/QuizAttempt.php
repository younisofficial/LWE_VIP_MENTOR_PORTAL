<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id', 'user_id', 'question_order', 'answers', 'started_at', 'deadline_at', 'submitted_at',
        'score', 'max_score', 'percent', 'passed',
    ];

    protected function casts(): array
    {
        return [
            'question_order' => 'array', 'answers' => 'array', 'started_at' => 'datetime', 'deadline_at' => 'datetime',
            'submitted_at' => 'datetime', 'score' => 'float', 'percent' => 'float', 'passed' => 'boolean', 'max_score' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->submitted_at === null;
    }

    /** Past the time limit (with 30 seconds of grace for slow internet). */
    public function isPastDeadline(): bool
    {
        return $this->deadline_at !== null && now()->greaterThan($this->deadline_at->copy()->addSeconds(30));
    }
}
