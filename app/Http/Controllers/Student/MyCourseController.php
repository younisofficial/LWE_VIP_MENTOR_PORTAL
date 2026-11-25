<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Inside an enrolled course: curriculum, lessons, mark complete, download attachments. */
class MyCourseController extends Controller
{
    public function show(Request $request, Course $course): Response|RedirectResponse
    {
        $enrollment = $this->enrollmentOrRedirect($request, $course);
        if ($enrollment instanceof RedirectResponse) {
            return $enrollment;
        }

        $user = $request->user();
        $done = $user->completedLessons()->where('lessons.course_id', $course->id)->pluck('lessons.id')->all();
        $course->load('modules.lessons');

        $total = $course->modules->sum(fn ($m) => $m->lessons->count());

        return Inertia::render('Student/MyCourses/Show', [
            'course' => $course->only('id', 'title', 'slug', 'is_vip'),
            'batch' => $enrollment->batch?->only('name'),
            'progress' => $total ? (int) round(count($done) / $total * 100) : 0,
            'modules' => $course->modules->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'lessons' => $m->lessons->map(fn (Lesson $l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'type' => $l->type,
                    'duration_minutes' => $l->duration_minutes,
                    'done' => in_array($l->id, $done, true),
                    'locked_reason' => $l->lockReasonFor($user),
                ]),
            ]),
        ]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson): Response|RedirectResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);
        $enrollment = $this->enrollmentOrRedirect($request, $course, $lesson);
        if ($enrollment instanceof RedirectResponse) {
            return $enrollment;
        }

        if ($reason = $lesson->lockReasonFor($request->user())) {
            return redirect()->route('student.my-courses.show', $course->slug)->with('error', $reason);
        }

        return Inertia::render('Student/MyCourses/Lesson', [
            'course' => $course->only('id', 'title', 'slug'),
            'lesson' => [
                ...$lesson->only('id', 'title', 'type', 'summary', 'body', 'video_url', 'duration_minutes', 'attachment_name'),
                'done' => $request->user()->completedLessons()->where('lessons.id', $lesson->id)->exists(),
                'has_attachment' => (bool) $lesson->attachment_path,
            ],
            'watermark' => $request->user()->name.' · '.$request->user()->student_code,
        ]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);
        abort_unless($request->user()->hasActiveEnrollmentIn($course), 403);

        $request->user()->completedLessons()->syncWithoutDetaching([$lesson->id => ['completed_at' => now()]]);

        return back()->with('success', 'Lesson marked as complete.');
    }

    public function attachment(Request $request, Course $course, Lesson $lesson): StreamedResponse
    {
        abort_unless($lesson->course_id === $course->id && $lesson->attachment_path, 404);
        abort_unless($lesson->is_preview || $request->user()->hasActiveEnrollmentIn($course) || $request->user()->isStaff(), 403);

        return Storage::disk('local')->download($lesson->attachment_path, $lesson->attachment_name);
    }

    /** Active enrollment, a preview lesson, or staff → allowed. Otherwise send them to the right place. */
    private function enrollmentOrRedirect(Request $request, Course $course, ?Lesson $lesson = null): Enrollment|RedirectResponse
    {
        $enrollment = $request->user()->enrollments()->with('batch')->where('course_id', $course->id)->first();

        if ($enrollment && in_array($enrollment->status, ['active', 'completed'], true)) {
            return $enrollment;
        }

        if ($lesson?->is_preview || $request->user()->isStaff()) {
            return $enrollment ?? new Enrollment(['course_id' => $course->id, 'status' => 'active']);
        }

        if ($enrollment && $enrollment->status === 'pending_payment') {
            return redirect()->route('student.payments.create', $enrollment)->with('error', 'Upload your payment to unlock this course.');
        }

        return redirect()->route('student.courses.show', $course->slug)->with('error', 'Enroll in this course first.');
    }
}
