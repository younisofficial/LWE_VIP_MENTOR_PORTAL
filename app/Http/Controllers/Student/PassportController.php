<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\ProfileAi;
use App\Services\PassportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The student's own Skill Passport + "what is left to finish each course". */
class PassportController extends Controller
{
    public function index(Request $request, PassportService $passports): Response
    {
        $user = $request->user();
        $data = $passports->build($user, refresh: true);
        $enrollments = $user->enrollments()->with('course:id,title')->whereIn('status', ['active', 'completed'])->get();

        return Inertia::render('Student/Passport/Index', [
            ...$data,
            'progress' => $enrollments->map(fn ($e) => [
                'course' => $e->course->title,
                'status' => $e->status,
                'progress_status' => $e->progress_status,
                'notes' => $e->progress_notes ?? [],
                'min_attendance' => $e->course->min_attendance_percent ?? null,
            ]),
            'publicUrl' => route('passport.show', $user->passportCode()),
            'isPublic' => (bool) $user->passport_public,
            'canAskAi' => ! $user->passport_summary_at || $user->passport_summary_at->lt(now()->subHours(12)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'passport_public' => ['required', 'boolean'],
            'passport_summary' => ['nullable', 'string', 'max:800'],
        ]);
        $request->user()->update($data);

        return back()->with('success', $data['passport_public'] ? 'Saved. Your passport link is public.' : 'Saved. Your passport is now private.');
    }

    /** AI writes the summary from the real record (max twice a day). */
    public function ai(Request $request, PassportService $passports, ProfileAi $ai): RedirectResponse
    {
        $user = $request->user();
        if ($user->passport_summary_at && $user->passport_summary_at->gt(now()->subHours(12))) {
            return back()->with('error', 'You can ask AI again in a few hours.');
        }
        $language = $request->validate(['language' => ['required', Rule::in(['english', 'roman_urdu', 'urdu'])]])['language'];

        try {
            $summary = $ai->passportSummary($passports->build($user), $language, $user);
        } catch (AiUnavailable $e) {
            return back()->with('error', $e->getMessage());
        }
        $user->update(['passport_summary' => $summary, 'passport_summary_at' => now()]);

        return back()->with('success', 'AI wrote your summary. Read it and change anything you like.');
    }
}
