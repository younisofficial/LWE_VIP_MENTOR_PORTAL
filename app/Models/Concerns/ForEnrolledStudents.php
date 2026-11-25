<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * For course content with an optional batch (tasks, quizzes):
 * a student sees it when it is published, they are enrolled in the course,
 * and it is for every batch or for their batch.
 */
trait ForEnrolledStudents
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $table = $this->getTable();

        return $query->where("{$table}.status", 'published')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('enrollments')
                ->whereColumn('enrollments.course_id', "{$table}.course_id")
                ->where('enrollments.user_id', $user->id)
                ->whereIn('enrollments.status', ['active', 'completed'])
                ->where(fn ($b) => $b->whereNull("{$table}.batch_id")->orWhereColumn('enrollments.batch_id', "{$table}.batch_id")));
    }

    /** Students who should see this item. */
    public function audience()
    {
        return User::whereIn('id', \App\Models\Enrollment::where('course_id', $this->course_id)
            ->whereIn('status', ['active', 'completed'])
            ->when($this->batch_id, fn ($q) => $q->where('batch_id', $this->batch_id))
            ->select('user_id'));
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }
}
