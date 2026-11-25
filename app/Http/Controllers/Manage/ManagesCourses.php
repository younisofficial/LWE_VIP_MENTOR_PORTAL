<?php

namespace App\Http\Controllers\Manage;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Shared helpers: admins see every course, mentors only theirs. */
trait ManagesCourses
{
    protected function authorizeCourse(User $user, int $courseId): void
    {
        abort_unless($user->canManageCourse($courseId), 403, 'This course is not yours to manage.');
    }

    /** Limit a query with a course_id column to the user's courses. */
    protected function scopeToUser(Builder $query, User $user, string $column = 'course_id'): Builder
    {
        $ids = $user->manageableCourseIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /** Courses with their lessons and batches, for form dropdowns. */
    protected function courseOptions(User $user): array
    {
        $ids = $user->manageableCourseIds();
        $courses = Course::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('title')->get(['id', 'title']);
        $courseIds = $courses->pluck('id');

        return [
            'courses' => $courses,
            'lessons' => Lesson::whereIn('course_id', $courseIds)->orderBy('position')->get(['id', 'course_id', 'title']),
            'batches' => Batch::with('courses:id')->whereHas('courses', fn ($q) => $q->whereIn('courses.id', $courseIds))
                ->whereIn('status', ['upcoming', 'running'])->orderBy('name')->get(['id', 'name'])
                ->map(fn (Batch $b) => ['id' => $b->id, 'name' => $b->name, 'course_ids' => $b->courses->pluck('id')->values()]),
        ];
    }
}
