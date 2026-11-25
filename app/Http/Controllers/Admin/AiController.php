<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Payment;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\CourseAi;
use App\Services\Ai\PaymentAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin AI tools. Everything AI writes is a draft the admin can change. */
class AiController extends Controller
{
    private const LANGUAGES = ['english', 'roman_urdu', 'urdu'];

    /** Draft for every AI-fillable field of the course wizard. JSON; nothing is saved. */
    public function courseDraft(Request $request, CourseAi $ai): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'level' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'short_description' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'language' => ['required', Rule::in(self::LANGUAGES)],
        ], ['title.required' => 'Write the course title first.']);

        $categories = Course::query()->whereNotNull('category')->distinct()->pluck('category')->take(30)->all();

        try {
            return response()->json($ai->draft($data['title'], $data['level'] ?? null, $data['short_description'] ?? null,
                $data['notes'] ?? null, $data['language'], $request->user(), $categories));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Generate modules + lessons and add them to the course (after any existing ones). */
    public function courseOutline(Request $request, Course $course, CourseAi $ai): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:4000'],
            'weeks' => ['required', 'integer', 'between:1,26'],
            'language' => ['required', Rule::in(self::LANGUAGES)],
        ]);

        try {
            $modules = $ai->outline($course, $data['notes'] ?? null, $data['weeks'], $data['language'], $request->user());
        } catch (AiUnavailable $e) {
            return back()->with('error', $e->getMessage());
        }

        $count = 0;
        DB::transaction(function () use ($course, $modules, &$count) {
            $mPos = (int) $course->modules()->max('position') + 1;
            foreach ($modules as $m) {
                $module = $course->modules()->create(['title' => $m['title'], 'position' => $mPos++]);
                foreach ($m['lessons'] as $i => $l) {
                    $module->lessons()->create([...$l, 'course_id' => $course->id, 'position' => $i]);
                    $count++;
                }
            }
        });

        activity('ai')->causedBy($request->user())->performedOn($course)->log("ai.outline: {$count} lessons added");

        return back()->with('success', "AI added ".count($modules)." modules and {$count} lessons. Review and edit them below.");
    }

    /** Summary + notes for one lesson. JSON for the lesson dialog. */
    public function lessonContent(Request $request, Course $course, CourseAi $ai): JsonResponse
    {
        abort_unless($request->user()->canManageCourse($course), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(Lesson::TYPES)],
            'hint' => ['nullable', 'string', 'max:1000'],
            'language' => ['required', Rule::in(self::LANGUAGES)],
        ]);

        try {
            return response()->json($ai->lesson($course, $data['title'], $data['type'], $data['hint'] ?? null, $data['language'], $request->user()));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** (Re)run the AI payment check from the review dialog. */
    public function paymentCheck(Payment $payment, PaymentAi $ai): RedirectResponse
    {
        try {
            $ai->check($payment);
        } catch (AiUnavailable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AI check done.');
    }
}
