<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use App\Services\BatchCourses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BatchController extends Controller
{
    public function index(Request $request): Response
    {
        $batches = Batch::query()
            ->with(['courses:id,title', 'courses.mentors:id,name', 'mentor:id,name'])
            ->withCount(['enrollments as students_count' => fn ($q) => $q->whereIn('status', ['active', 'pending_payment', 'completed'])])
            ->when($request->course_id, fn ($q, $id) => $q->whereHas('courses', fn ($c) => $c->whereKey($id)))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('starts_on')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Batch $b) {
                // Everyone who teaches here: the lead mentor plus the mentors of every course.
                $mentors = collect($b->mentor ? [$b->mentor] : [])
                    ->merge($b->courses->flatMap->mentors)->unique('id')->values();

                return [
                    ...$b->toArray(),
                    'courses' => $b->courses->map->only('id', 'title')->values(),
                    'mentors' => $mentors->map->only('id', 'name')->values(),
                ];
            });

        return Inertia::render('Admin/Batches/Index', [
            'batches' => $batches,
            'filters' => $request->only('course_id', 'status'),
            'courses' => Course::with('mentors:id,name')->orderBy('title')->get(['id', 'title'])
                ->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'mentors' => $c->mentors->map->only('id', 'name')->values()]),
            'mentors' => User::role(User::ROLE_MENTOR)->orderBy('name')->get(['id', 'name']),
            'days' => Batch::DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $courseIds = $data['course_ids'];
        unset($data['course_ids'], $data['enroll_existing']);
        $batch = Batch::create([...$data, 'course_id' => $courseIds[0]]);
        $batch->setCourses($courseIds);

        return back()->with('success', 'Batch created.');
    }

    public function update(Request $request, Batch $batch, BatchCourses $batchCourses): RedirectResponse
    {
        $data = $this->validated($request);
        $enrollExisting = $request->boolean('enroll_existing');
        $courseIds = $data['course_ids'];
        unset($data['course_ids'], $data['enroll_existing']);

        // A course with students in this batch cannot be taken out of it.
        $inUse = $batch->enrollments()->whereIn('status', ['pending_payment', 'active', 'completed'])
            ->whereNotIn('course_id', $courseIds)->with('course:id,title')->first();
        if ($inUse) {
            return back()->withErrors(['course_ids' => "{$inUse->course->title} has students in this batch, so it cannot be removed."]);
        }

        $before = $batch->courses()->pluck('courses.id')->map(fn ($id) => (int) $id)->all();
        $batch->update($data);
        $batch->setCourses($courseIds);

        // New courses in the batch: optionally give them to the students already in it.
        $added = array_diff(array_map('intval', $courseIds), $before);
        $enrolled = 0;
        if ($enrollExisting && $added) {
            foreach (Course::whereIn('id', $added)->get() as $course) {
                $enrolled += $batchCourses->enrollStudents($batch, $course, $request->user());
            }
        }

        return back()->with('success', $enrolled ? "Batch saved. {$enrolled} course enrollments added for the batch's students." : 'Batch saved.');
    }

    public function destroy(Batch $batch): RedirectResponse
    {
        if ($batch->enrollments()->exists()) {
            return back()->with('error', 'This batch has students. Mark it cancelled instead of deleting.');
        }
        $batch->delete();

        return back()->with('success', 'Batch deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'course_ids' => ['required', 'array', 'min:1', 'max:100'],
            'course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:100'],
            'mentor_id' => ['nullable', 'exists:users,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'class_days' => ['array'],
            'class_days.*' => [Rule::in(Batch::DAYS)],
            'class_time' => ['nullable', 'date_format:H:i'],
            'class_duration_minutes' => ['required', 'integer', 'between:15,480'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['upcoming', 'running', 'completed', 'cancelled'])],
        ], ['course_ids.required' => 'Choose at least one course for this batch.', 'course_ids.min' => 'Choose at least one course for this batch.']);
    }
}
