<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaQuestion extends Model
{
    protected $fillable = [
        'batch_id', 'course_id', 'lesson_id', 'user_id', 'title', 'body', 'images', 'status', 'is_pinned',
        'accepted_answer_id', 'answers_count', 'last_activity_at',
    ];

    protected $attributes = ['status' => 'open', 'is_pinned' => false, 'answers_count' => 0];

    protected function casts(): array
    {
        return ['images' => 'array', 'is_pinned' => 'boolean', 'last_activity_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QaAnswer::class)->orderByDesc('is_accepted')->orderBy('created_at');
    }
}
