<?php

namespace App\Models;

use App\Models\Concerns\ForEnrolledStudents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use ForEnrolledStudents;

    public const SUBMISSION_TYPES = ['any', 'text', 'file', 'link'];

    protected $fillable = [
        'course_id', 'lesson_id', 'batch_id', 'title', 'instructions', 'rubric', 'attachment_path', 'attachment_name',
        'submission_type', 'max_marks', 'due_at', 'allow_late', 'status', 'published_at', 'created_by',
    ];

    protected $attributes = ['submission_type' => 'any', 'max_marks' => 10, 'allow_late' => true, 'status' => 'draft'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'published_at' => 'datetime', 'allow_late' => 'boolean', 'max_marks' => 'integer'];
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

    public function submissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && now()->greaterThan($this->due_at);
    }

    /** Can a student still submit (or re-submit)? */
    public function acceptsSubmissions(): bool
    {
        return $this->status === 'published' && (! $this->isOverdue() || $this->allow_late);
    }
}
