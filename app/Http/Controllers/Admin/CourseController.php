<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Course wizard: 6 short steps (basic → details → skills → fee → completion → publish).
 * Step 1 creates the course as a draft; every later step saves and moves to the next one.
 */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $courses = Course::query()
            ->withCount(['lessons', 'enrollments', 'batches'])
            ->with('mentors:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Courses/Wizard', [
            'course' => null,
            'step' => 'basic',
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateStep($request, 'basic');
        $course = new Course;
        $this->fillBasic($course, $data, $request);
        $course->created_by = $request->user()->id;
        $course->wizard_step = 2;
        $course->save();
        $course->mentors()->sync($data['mentor_ids'] ?? []);

        return redirect()->route('admin.courses.edit', [$course, 'step' => 'details'])
            ->with('success', 'Course created as a draft. Now add the details.');
    }

    public function edit(Request $request, Course $course): Response
    {
        $step = in_array($request->query('step'), Course::WIZARD_STEPS, true) ? $request->query('step') : 'basic';
        $course->load('mentors:id', 'skills:id,name');

        return Inertia::render('Admin/Courses/Wizard', [
            'course' => [
                ...$course->toArray(),
                'mentor_ids' => $course->mentors->pluck('id'),
                'skill_names' => $course->skills->pluck('name'),
                'lessons_count' => $course->lessons()->count(),
                'batches_count' => $course->batches()->count(),
                'batch_ids' => \App\Models\Batch::whereHas('courses', fn ($q) => $q->whereKey($course->id))->pluck('id'),
            ],
            'step' => $step,
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $step = $request->input('step');
        if (! in_array($step, Course::WIZARD_STEPS, true)) {
            abort(422, 'Unknown step');
        }

        // Safety: a step must arrive with its own fields. Otherwise (e.g. an old page sending the
        // wrong step name) we would overwrite saved data with blanks.
        $expected = [
            'details' => ['description', 'what_you_learn', 'requirements', 'intro_video_url', 'faqs'],
            'fee' => ['is_free', 'fee'],
            'completion' => ['min_attendance_percent', 'weight_attendance'],
            'publish' => ['status'],
        ][$step] ?? null;
        if ($expected && ! $request->hasAny($expected)) {
            return back()->with('error', 'The page was out of date. Please refresh and save again.');
        }

        $data = $this->validateStep($request, $step, $course);

        match ($step) {
            'basic' => (function () use ($course, $data, $request) {
                $this->fillBasic($course, $data, $request);
                $course->save();
                $course->mentors()->sync($data['mentor_ids'] ?? []);
            })(),
            'details' => $course->update([
                'description' => $data['description'] ?? null,
                'what_you_learn' => array_values(array_filter($data['what_you_learn'] ?? [])),
                'requirements' => array_values(array_filter($data['requirements'] ?? [])),
                'intro_video_url' => $data['intro_video_url'] ?? null,
                'faqs' => array_values(array_filter($data['faqs'] ?? [], fn ($f) => filled($f['q'] ?? null))),
            ]),
            'skills' => $course->skills()->sync(
                collect($data['skill_names'] ?? [])->map(fn ($n) => trim($n))->filter()->unique()
                    ->map(fn ($name) => Skill::firstOrCreate(['name' => $name])->id)
            ),
            'fee' => $course->update([
                'fee' => ($data['is_free'] ?? false) ? null : $data['fee'],
                'coupons_allowed' => $data['coupons_allowed'] ?? false,
            ]),
            'completion' => $course->update($data),
            'publish' => (function () use ($course, $data, $request) {
                $course->update(['status' => $data['status']]);
                $this->saveBatches($course, $data['batch_ids'] ?? [], $request->boolean('enroll_existing'), $request->user());
            })(),
        };

        $index = array_search($step, Course::WIZARD_STEPS, true);
        $course->update(['wizard_step' => max($course->wizard_step, min($index + 2, count(Course::WIZARD_STEPS)))]);

        if ($step === 'publish') {
            return redirect()->route('admin.courses.index')
                ->with('success', ($data['status'] === 'published' ? 'Course published.' : 'Course saved.').' Add lessons any time from the Lessons page.');
        }

        $next = Course::WIZARD_STEPS[$index + 1];

        return redirect()->route('admin.courses.edit', [$course, 'step' => $next])->with('success', 'Saved.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        if ($course->enrollments()->whereIn('status', ['active', 'pending_payment'])->exists()) {
            return back()->with('error', 'This course has active students. Unpublish it instead of deleting.');
        }
        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Course deleted.');
    }

    // ------------------------------------------------------------------

    private function validateStep(Request $request, string $step, ?Course $course = null): array
    {
        $rules = match ($step) {
            'basic' => [
                'title' => ['required', 'string', 'max:150'],
                'short_description' => ['nullable', 'string', 'max:300'],
                'category' => ['nullable', 'string', 'max:80'],
                'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
                'is_vip' => ['boolean'],
                'is_masterclass' => ['boolean'],
                'mentor_ids' => ['array'],
                'mentor_ids.*' => ['integer', Rule::exists('model_has_roles', 'model_id')->where('role_id', $this->mentorRoleId())],
                'thumbnail' => ['nullable', 'image', 'max:'.config('lwe.uploads.max_image_kb')],
            ],
            'details' => [
                'description' => ['nullable', 'string', 'max:20000'],
                'what_you_learn' => ['array', 'max:20'],
                'what_you_learn.*' => ['nullable', 'string', 'max:500'],
                'requirements' => ['array', 'max:20'],
                'requirements.*' => ['nullable', 'string', 'max:500'],
                'intro_video_url' => ['nullable', 'url', 'max:500'],
                'faqs' => ['array', 'max:20'],
                'faqs.*.q' => ['nullable', 'string', 'max:500'],
                'faqs.*.a' => ['nullable', 'string', 'max:3000'],
            ],
            'skills' => [
                'skill_names' => ['array', 'max:15'],
                'skill_names.*' => ['string', 'max:60'],
            ],
            'fee' => [
                'is_free' => ['boolean'],
                'fee' => ['exclude_if:is_free,true', 'required', 'numeric', 'min:1', 'max:10000000'],
                'coupons_allowed' => ['boolean'],
            ],
            'completion' => [
                'min_attendance_percent' => ['required', 'integer', 'between:0,100'],
                'require_compulsory_tasks' => ['boolean'],
                'min_quiz_percent' => ['required', 'integer', 'between:0,100'],
                'weight_attendance' => ['required', 'integer', 'between:0,100'],
                'weight_tasks' => ['required', 'integer', 'between:0,100'],
                'weight_quiz' => ['required', 'integer', 'between:0,100'],
            ],
            'publish' => [
                'status' => ['required', Rule::in(['draft', 'published', 'private'])],
                'batch_ids' => ['array'],
                'batch_ids.*' => ['integer', 'exists:batches,id'],
                'enroll_existing' => ['boolean'],
            ],
        };

        $data = $request->validate($rules, [
            'what_you_learn.*.max' => 'Point :position is too long (max :max characters).',
            'requirements.*.max' => 'Requirement :position is too long (max :max characters).',
            'faqs.*.q.max' => 'FAQ question :position is too long.',
            'faqs.*.a.max' => 'FAQ answer :position is too long.',
        ]);

        if ($step === 'completion' && ($data['weight_attendance'] + $data['weight_tasks'] + $data['weight_quiz']) !== 100) {
            throw ValidationException::withMessages(['weight_attendance' => 'Attendance + tasks + quiz weight must add up to 100.']);
        }

        return $data;
    }

    private function fillBasic(Course $course, array $data, Request $request): void
    {
        $course->fill([
            'title' => $data['title'],
            'short_description' => $data['short_description'] ?? null,
            'category' => $data['category'] ?? null,
            'level' => $data['level'],
            'is_vip' => $data['is_vip'] ?? false,
            'is_masterclass' => $data['is_masterclass'] ?? false,
        ]);

        if ($course->exists && $course->isDirty('title')) {
            $course->slug = Course::uniqueSlug($data['title'], $course->id);
        }

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail_path) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }
            $course->thumbnail_path = $request->file('thumbnail')->store('course-thumbnails', 'public');
        }
    }

    private function mentorRoleId(): int
    {
        return (int) \Spatie\Permission\Models\Role::findByName(User::ROLE_MENTOR)->id;
    }

    /**
     * Publish step: which batches teach this course. Ticked batches get it (and, if asked, their
     * current students get it too). Unticked batches lose it, unless students in that batch study it
     * or it is the batch's only course.
     */
    private function saveBatches(Course $course, array $batchIds, bool $enrollExisting, User $by): void
    {
        $service = app(\App\Services\BatchCourses::class);
        $batchIds = array_map('intval', $batchIds);
        foreach (\App\Models\Batch::whereIn('id', $batchIds)->get() as $batch) {
            $service->add($batch, $course, $enrollExisting, $by);
        }

        $removed = \App\Models\Batch::whereHas('courses', fn ($q) => $q->whereKey($course->id))->whereNotIn('id', $batchIds)->get();
        foreach ($removed as $batch) {
            $hasStudents = $batch->enrollments()->where('course_id', $course->id)->whereIn('status', ['pending_payment', 'active', 'completed'])->exists();
            $others = $batch->courses()->where('courses.id', '!=', $course->id)->pluck('courses.id')->all();
            if (! $hasStudents && $others) {
                $batch->setCourses($others);
            }
        }
    }

    private function options(): array
    {
        return [
            'mentors' => User::role(User::ROLE_MENTOR)->orderBy('name')->get(['id', 'name']),
            'categories' => Course::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'skills' => Skill::orderBy('name')->pluck('name'),
            'steps' => Course::WIZARD_STEPS,
            'batches' => \App\Models\Batch::whereIn('status', ['upcoming', 'running'])->withCount('courses')->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'status' => $b->status, 'courses_count' => $b->courses_count]),
        ];
    }
}
