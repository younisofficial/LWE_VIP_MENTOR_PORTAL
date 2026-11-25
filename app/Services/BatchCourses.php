<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * One batch, many courses. Adding a course to a batch can also give that course to every
 * student already studying in the batch, so a batch of 180 students can get 17 courses
 * without enrolling anyone again by hand.
 */
class BatchCourses
{
    public function __construct(private EnrollmentService $enrollments) {}

    /** Students currently studying in the batch (any of its courses). */
    public function studentIds(Batch $batch): array
    {
        return $batch->enrollments()->whereIn('status', ['active', 'completed'])
            ->distinct()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Puts the course in the batch. With $enrollStudents, the batch's students get it too. Returns how many were enrolled. */
    public function add(Batch $batch, Course $course, bool $enrollStudents, ?User $by = null): int
    {
        $batch->courses()->syncWithoutDetaching([$course->id]);

        return $enrollStudents ? $this->enrollStudents($batch, $course, $by) : 0;
    }

    public function enrollStudents(Batch $batch, Course $course, ?User $by = null): int
    {
        $count = 0;
        foreach ($this->studentIds($batch) as $userId) {
            $taken = Enrollment::where('user_id', $userId)->where('course_id', $course->id)
                ->where('status', '!=', 'cancelled')->exists();
            if ($taken) {
                continue;
            }
            try {
                // Already in the batch, so no seat check or fee: the batch fee covers its courses.
                $this->enrollments->enroll(User::find($userId), $course, $batch, null, 'admin', $by, activateNow: true);
                $count++;
            } catch (ValidationException) {
                // e.g. enrolled meanwhile; skip this student
            }
        }

        return $count;
    }
}
