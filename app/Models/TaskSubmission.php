<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSubmission extends Model
{
    protected $fillable = [
        'task_id', 'user_id', 'body', 'link', 'file_path', 'file_name', 'submitted_at', 'is_late', 'status',
        'marks', 'feedback', 'ai_marks', 'ai_feedback', 'graded_by', 'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime', 'graded_at' => 'datetime', 'is_late' => 'boolean',
            'marks' => 'float', 'ai_marks' => 'float',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
