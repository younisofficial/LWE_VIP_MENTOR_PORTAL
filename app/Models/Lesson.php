<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    public const TYPES = ['live_class', 'recorded_video', 'reading', 'task', 'quiz'];

    protected $fillable = [
        'course_id', 'course_module_id', 'title', 'type', 'summary', 'body', 'video_url',
        'attachment_path', 'attachment_name', 'duration_minutes', 'position',
        'unlock_type', 'unlock_at', 'is_preview',
    ];

    protected function casts(): array
    {
        return [
            'unlock_at' => 'datetime',
            'is_preview' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    /** Previous lesson in the course order (module position, then lesson position). */
    public function previousLesson(): ?Lesson
    {
        $ordered = Lesson::query()
            ->where('lessons.course_id', $this->course_id)
            ->join('course_modules', 'course_modules.id', '=', 'lessons.course_module_id')
            ->orderBy('course_modules.position')
            ->orderBy('lessons.position')
            ->pluck('lessons.id')
            ->values();

        $index = $ordered->search($this->id);

        return ($index === false || $index === 0) ? null : Lesson::find($ordered[$index - 1]);
    }

    /**
     * Drip rule check. Returns null when unlocked, otherwise a short reason for the student.
     */
    public function lockReasonFor(User $user): ?string
    {
        return match ($this->unlock_type) {
            'date' => ($this->unlock_at && $this->unlock_at->isFuture())
                ? 'Unlocks on '.$this->unlock_at->timezone(config('lwe.timezone'))->format('d M Y, g:i A')
                : null,
            'after_previous' => (function () use ($user) {
                $prev = $this->previousLesson();
                if (! $prev) {
                    return null;
                }
                $done = $user->completedLessons()->where('lessons.id', $prev->id)->exists();

                return $done ? null : 'Complete "'.$prev->title.'" first';
            })(),
            default => null,
        };
    }
}
