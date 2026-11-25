<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Course → Modules → Lessons, with drag-and-drop ordering.
 * Admins use it under /admin; mentors use the same pages under /manage, for their own courses only.
 */
class CurriculumController extends Controller
{
    private function allow(Request $request, int $courseId): void
    {
        abort_unless($request->user()->canManageCourse($courseId), 403, 'This course is not yours to manage.');
    }

    private function area(Request $request): string
    {
        return $request->routeIs('manage.*') ? 'manage' : 'admin';
    }

    public function show(Request $request, Course $course): Response
    {
        $this->allow($request, $course->id);
        $course->load(['modules.lessons' => fn ($q) => $q->orderBy('position')]);

        return Inertia::render('Admin/Courses/Curriculum', [
            'course' => $course->only('id', 'title', 'slug', 'status', 'is_free', 'fee'),
            'modules' => $course->modules->map(fn (CourseModule $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'lessons' => $m->lessons->map(fn (Lesson $l) => [
                    ...$l->only('id', 'title', 'type', 'summary', 'body', 'video_url', 'duration_minutes', 'unlock_type', 'is_preview', 'attachment_name'),
                    'unlock_at' => $l->unlock_at?->timezone(config('lwe.timezone'))->format('Y-m-d\TH:i'),
                ]),
            ]),
            'lessonTypes' => Lesson::TYPES,
            'area' => $this->area($request),
        ]);
    }

    /** All lessons of all courses, with a form that first asks "which course?". */
    public function lessons(Request $request): Response
    {
        $ids = $request->user()->manageableCourseIds(); // null = admin, all courses
        $lessons = Lesson::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('course_id', $ids))
            ->with(['course:id,title', 'module:id,title'])
            ->when($request->course_id, fn ($q, $id) => $q->where('course_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Lesson $l) => [
                'id' => $l->id,
                'title' => $l->title,
                'type' => $l->type,
                'course' => $l->course?->title,
                'course_id' => $l->course_id,
                'module' => $l->module?->title,
                'duration' => $l->duration_minutes,
                'is_preview' => $l->is_preview,
                'date' => $l->created_at->format('d M Y'),
            ]);

        return Inertia::render('Admin/Lessons/Index', [
            'lessons' => $lessons,
            'filters' => $request->only('course_id', 'search'),
            'courses' => Course::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('title')->get(['id', 'title']),
            'modules' => CourseModule::when($ids !== null, fn ($q) => $q->whereIn('course_id', $ids))->orderBy('position')->get(['id', 'course_id', 'title']),
            'lessonTypes' => Lesson::TYPES,
            'area' => $this->area($request),
        ]);
    }

    /** New lesson from the Lessons page: course chosen first, module optional. */
    public function storeLessonForCourse(Request $request): RedirectResponse
    {
        $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'course_module_id' => ['nullable', 'integer'],
            'new_module' => ['nullable', 'string', 'max:150'],
        ]);
        $course = Course::findOrFail($request->integer('course_id'));
        $this->allow($request, $course->id);
        $data = $this->validateLesson($request);

        $module = match (true) {
            filled($request->input('new_module')) => $course->modules()->create([
                'title' => $request->input('new_module'),
                'position' => (int) $course->modules()->max('position') + 1,
            ]),
            $request->filled('course_module_id') => $course->modules()->findOrFail($request->integer('course_module_id')),
            // No module chosen: use the course's last module, or create a first one.
            default => $course->modules()->orderByDesc('position')->first()
                ?? $course->modules()->create(['title' => 'Lessons', 'position' => 0]),
        };

        $lesson = new Lesson([
            ...$data,
            'course_id' => $course->id,
            'course_module_id' => $module->id,
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);
        $this->handleAttachment($request, $lesson);
        $lesson->save();

        return back()->with('success', "Lesson added to {$course->title} → {$module->title}.");
    }

    public function storeModule(Request $request, Course $course): RedirectResponse
    {
        $this->allow($request, $course->id);
        $data = $request->validate(['title' => ['required', 'string', 'max:150']]);
        $course->modules()->create([
            'title' => $data['title'],
            'position' => (int) $course->modules()->max('position') + 1,
        ]);

        return back()->with('success', 'Module added.');
    }

    public function updateModule(Request $request, CourseModule $module): RedirectResponse
    {
        $this->allow($request, $module->course_id);
        $module->update($request->validate(['title' => ['required', 'string', 'max:150']]));

        return back()->with('success', 'Module renamed.');
    }

    public function destroyModule(Request $request, CourseModule $module): RedirectResponse
    {
        $this->allow($request, $module->course_id);
        $module->delete();

        return back()->with('success', 'Module deleted.');
    }

    public function storeLesson(Request $request, CourseModule $module): RedirectResponse
    {
        $this->allow($request, $module->course_id);
        $data = $this->validateLesson($request);
        $lesson = new Lesson([
            ...$data,
            'course_id' => $module->course_id,
            'course_module_id' => $module->id,
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);
        $this->handleAttachment($request, $lesson);
        $lesson->save();

        return back()->with('success', 'Lesson added.');
    }

    public function updateLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->allow($request, $lesson->course_id);
        $lesson->fill($this->validateLesson($request));
        $this->handleAttachment($request, $lesson);
        $lesson->save();

        return back()->with('success', 'Lesson saved.');
    }

    public function destroyLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->allow($request, $lesson->course_id);
        if ($lesson->attachment_path) {
            Storage::disk('local')->delete($lesson->attachment_path);
        }
        $lesson->delete();

        return back()->with('success', 'Lesson deleted.');
    }

    /**
     * Save the full order after a drag-and-drop.
     * Payload: modules: [{id, lessons: [lessonId, ...]}, ...]  (lessons may move between modules)
     */
    public function reorder(Request $request, Course $course): RedirectResponse
    {
        $this->allow($request, $course->id);
        $data = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.id' => ['required', 'integer', Rule::exists('course_modules', 'id')->where('course_id', $course->id)],
            'modules.*.lessons' => ['array'],
            'modules.*.lessons.*' => ['integer', Rule::exists('lessons', 'id')->where('course_id', $course->id)],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['modules'] as $mIndex => $m) {
                CourseModule::whereKey($m['id'])->update(['position' => $mIndex]);
                foreach ($m['lessons'] ?? [] as $lIndex => $lessonId) {
                    Lesson::whereKey($lessonId)->update(['course_module_id' => $m['id'], 'position' => $lIndex]);
                }
            }
        });

        return back()->with('success', 'Order saved.');
    }

    private function validateLesson(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(Lesson::TYPES)],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:50000'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,600'],
            'unlock_type' => ['required', Rule::in(['immediate', 'date', 'after_previous'])],
            'unlock_at' => ['nullable', 'required_if:unlock_type,date', 'date'],
            'is_preview' => ['boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,png,jpg,jpeg', 'max:'.config('lwe.uploads.max_attachment_kb')],
        ]);

        // The admin types local (Pakistan) time; store UTC.
        $data['unlock_at'] = ! empty($data['unlock_at']) && $data['unlock_type'] === 'date'
            ? \Carbon\Carbon::parse($data['unlock_at'], config('lwe.timezone'))->utc()
            : null;

        unset($data['attachment']);

        return $data;
    }

    private function handleAttachment(Request $request, Lesson $lesson): void
    {
        if (! $request->hasFile('attachment')) {
            return;
        }
        if ($lesson->attachment_path) {
            Storage::disk('local')->delete($lesson->attachment_path);
        }
        $file = $request->file('attachment');
        // Private disk: students download through a controller that checks enrollment.
        $lesson->attachment_path = $file->store('lesson-attachments', 'local');
        $lesson->attachment_name = $file->getClientOriginalName();
    }
}
