<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Services\CouponService;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Course list + course page + enroll (free = instant, paid = payment upload). */
class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $enrolledIds = $request->user()->enrollments()->where('status', '!=', 'cancelled')->pluck('course_id');

        $courses = Course::published()
            ->with('mentors:id,name')
            ->withCount('lessons')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->filter === 'free', fn ($q) => $q->where(fn ($w) => $w->whereNull('fee')->orWhere('fee', '<=', 0)))
            ->when($request->filter === 'vip', fn ($q) => $q->where('is_vip', true))
            ->latest()
            ->get()
            ->map(fn (Course $c) => [
                ...$c->only('id', 'title', 'slug', 'short_description', 'level', 'is_vip', 'is_masterclass', 'is_free', 'thumbnail_url', 'lessons_count'),
                'fee' => $c->fee ? (float) $c->fee : null,
                'mentors' => $c->mentors->pluck('name'),
                'enrolled' => $enrolledIds->contains($c->id),
            ]);

        return Inertia::render('Student/Courses/Index', [
            'courses' => $courses,
            'filters' => $request->only('search', 'filter'),
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        abort_unless($course->status === 'published', 404);

        $course->load(['mentors:id,name', 'skills:id,name', 'modules.lessons:id,course_module_id,title,type,duration_minutes,is_preview,position']);
        $enrollment = $request->user()->enrollments()->where('course_id', $course->id)->where('status', '!=', 'cancelled')->first();

        return Inertia::render('Student/Courses/Show', [
            'course' => [
                ...$course->only('id', 'title', 'slug', 'short_description', 'description', 'level', 'is_vip', 'is_masterclass',
                    'is_free', 'thumbnail_url', 'what_you_learn', 'requirements', 'intro_video_url', 'faqs', 'coupons_allowed'),
                'fee' => $course->fee ? (float) $course->fee : null,
                'mentors' => $course->mentors->pluck('name'),
                'skills' => $course->skills->pluck('name'),
                'modules' => $course->modules->map(fn ($m) => [
                    'title' => $m->title,
                    'lessons' => $m->lessons->map->only('title', 'type', 'duration_minutes', 'is_preview'),
                ]),
            ],
            'batches' => $course->openBatches()->with('mentor:id,name')->get()->map(fn (Batch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'starts_on' => $b->starts_on->format('d M Y'),
                'days' => collect($b->class_days ?? [])->map(fn ($d) => ucfirst($d))->implode(', '),
                'time' => $b->class_time ? \Carbon\Carbon::parse($b->class_time)->format('g:i A') : null,
                'mentor' => $b->mentor?->name,
                'full' => ! $b->hasFreeSeat(),
                'seats_left' => $b->seats ? max(0, $b->seats - $b->seatsTaken()) : null,
            ]),
            'enrollment' => $enrollment?->only('id', 'status', 'amount_due'),
        ]);
    }

    public function couponPreview(Request $request, Course $course, CouponService $coupons): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:40']]);

        return response()->json($coupons->preview((string) $request->input('code'), $course));
    }

    public function enroll(Request $request, Course $course, EnrollmentService $service): RedirectResponse
    {
        abort_unless($course->status === 'published', 404);

        $data = $request->validate([
            'batch_id' => [
                Rule::requiredIf(fn () => $course->openBatches()->exists()),
                'nullable',
                Rule::in($course->openBatches()->pluck('batches.id')->all()),
            ],
            'coupon' => ['nullable', 'string', 'max:40'],
        ], ['batch_id.required' => 'Please choose a batch.', 'batch_id.in' => 'Please choose an open batch.']);

        $batch = ! empty($data['batch_id']) ? Batch::find($data['batch_id']) : null;
        $enrollment = $service->enroll($request->user(), $course, $batch, $data['coupon'] ?? null);

        if ($enrollment->status === 'active') {
            return redirect()->route('student.my-courses.show', $course->slug)->with('success', 'You are enrolled. Start learning!');
        }

        return redirect()->route('student.payments.create', $enrollment)->with('success', 'Seat reserved. Upload your payment to unlock the course.');
    }
}
