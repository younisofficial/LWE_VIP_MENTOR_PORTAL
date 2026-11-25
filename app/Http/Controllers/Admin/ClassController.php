<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncZoomMeeting;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\ClassNotesAi;
use App\Services\ClassScheduler;
use App\Services\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Live classes: schedule, reschedule, cancel, attendance, AI notes. Mentors can open their own classes. */
class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $when = $request->input('when', 'upcoming');
        $tz = config('lwe.timezone');

        $sessions = ClassSession::with(['course:id,title', 'batch:id,name', 'mentor:id,name', 'zoomAccount:id,name'])
            ->when($request->batch_id, fn ($q, $id) => $q->where('batch_id', $id))
            ->when($when === 'upcoming', fn ($q) => $q->whereIn('status', ['scheduled', 'live'])->where('starts_at', '>=', now()->subHours(6))->orderBy('starts_at'))
            ->when($when === 'past', fn ($q) => $q->where(fn ($w) => $w->where('status', 'ended')->orWhere('starts_at', '<', now()->subHours(6)))->orderByDesc('starts_at'))
            ->when($when === 'cancelled', fn ($q) => $q->where('status', 'cancelled')->orderByDesc('starts_at'))
            ->paginate(30)->withQueryString()
            ->through(fn (ClassSession $s) => $this->row($s, $tz));

        return Inertia::render('Admin/Classes/Index', [
            'sessions' => $sessions,
            'filters' => ['when' => $when, 'batch_id' => $request->batch_id],
            'batches' => Batch::with('courses:id,title', 'courses.mentors:id')->whereIn('status', ['upcoming', 'running'])->orderBy('name')
                ->get(['id', 'name', 'course_id', 'mentor_id', 'class_days', 'class_time', 'class_duration_minutes'])
                ->map(fn (Batch $b) => [
                    ...$b->only('id', 'name', 'course_id', 'mentor_id', 'class_days', 'class_time', 'class_duration_minutes'),
                    'courses' => $b->courses->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'mentor_ids' => $c->mentors->pluck('id')->values()])->values(),
                ]),
            'mentors' => User::role(User::ROLE_MENTOR)->orderBy('name')->get(['id', 'name']),
            'lessons' => Lesson::query()->join('course_modules', 'course_modules.id', '=', 'lessons.course_module_id')
                ->orderBy('course_modules.position')->orderBy('lessons.position')
                ->get(['lessons.id', 'lessons.title', 'lessons.course_id', 'lessons.type', 'lessons.video_url', 'lessons.duration_minutes'])
                ->map(fn ($l) => [...$l->only('id', 'title', 'course_id', 'type', 'duration_minutes'), 'has_video' => filled($l->video_url)]),
            'days' => Batch::DAYS,
            'hasZoom' => \App\Models\ZoomAccount::where('is_active', true)->exists(),
        ]);
    }

    public function store(Request $request, ClassScheduler $scheduler): RedirectResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'start_date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'between:15,480'],
            'days' => ['array'],
            'days.*' => [Rule::in(Batch::DAYS)],
            'weeks' => ['required', 'integer', 'between:1,52'],
            'mentor_id' => ['nullable', 'exists:users,id'],
            'start_lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['nullable', 'string', 'max:190'],
            'kind' => ['nullable', Rule::in(['live', 'recorded'])],
        ]);

        $batch = Batch::findOrFail($data['batch_id']);
        $sessions = $scheduler->schedule($batch, $data, $request->user());
        if (($data['kind'] ?? 'live') === 'recorded') {
            return redirect()->route('admin.classes.index')->with('success', "{$sessions->count()} recorded lectures scheduled. They play inside the portal at the set time — attendance is automatic.");
        }
        $failed = $sessions->filter(fn ($s) => $s->fresh()->zoom_error)->count();

        return redirect()->route('admin.classes.index')->with(
            $failed ? 'error' : 'success',
            $failed ? "{$sessions->count()} classes saved, but Zoom could not create {$failed} meetings. Open a class and press \"Retry Zoom\"."
                : "{$sessions->count()} classes scheduled with Zoom meetings."
        );
    }

    public function show(Request $request, ClassSession $session): Response
    {
        $this->authorizeView($request->user(), $session);
        $session->load(['course:id,title', 'batch:id,name', 'mentor:id,name', 'zoomAccount:id,name', 'lesson:id,title']);
        $tz = config('lwe.timezone');

        $attendance = Attendance::where('class_session_id', $session->id)->get()->keyBy('user_id');
        $joins = $session->participants()->whereNotNull('user_id')->get()->groupBy('user_id');

        return Inertia::render('Admin/Classes/Show', [
            'session' => [
                ...$this->row($session, $tz),
                'lesson' => $session->lesson?->title,
                'has_recording' => filled($session->recording_url),
                'recording_url' => $request->user()->isStaff() ? $session->recording_url : null,
                'has_transcript' => filled($session->transcript),
                'ai_notes' => $session->ai_notes,
                'notes_published' => $session->notes_published,
                'cancel_reason' => $session->cancel_reason,
            ],
            'students' => $session->expectedStudents()->orderBy('name')->get(['id', 'name', 'phone', 'student_code'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'code' => $u->student_code,
                    'phone' => Phone::display($u->phone),
                    'status' => $attendance->get($u->id)?->status,
                    'minutes' => $attendance->get($u->id)?->minutes,
                    'source' => $attendance->get($u->id)?->source,
                    'joins' => $joins->get($u->id)?->count() ?? 0,
                ]),
            'unknownJoins' => $session->participants()->whereNull('user_id')->get(['display_name', 'joined_at', 'left_at'])
                ->map(fn ($p) => ['name' => $p->display_name, 'joined' => $p->joined_at->setTimezone($tz)->format('g:i A')]),
            'canManage' => $request->user()->isStaff(),
            'isMentorView' => ! $request->user()->isStaff(),
        ]);
    }

    public function reschedule(Request $request, ClassSession $session, ClassScheduler $scheduler): RedirectResponse
    {
        abort_if(in_array($session->status, ['ended', 'cancelled'], true), 422, 'This class is already over or cancelled.');
        $data = $request->validate([
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'between:15,480'],
        ]);
        $scheduler->reschedule($session, $data['date'], $data['time'], $data['duration'], $request->user());

        return back()->with('success', 'Class moved. Students have been told.');
    }

    public function cancel(Request $request, ClassSession $session, ClassScheduler $scheduler): RedirectResponse
    {
        abort_if(in_array($session->status, ['ended', 'cancelled'], true), 422, 'This class is already over or cancelled.');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $scheduler->cancel($session, $data['reason'] ?? '', $request->user());

        return back()->with('success', 'Class cancelled. Students have been told on WhatsApp.');
    }

    public function retryZoom(ClassSession $session): RedirectResponse
    {
        SyncZoomMeeting::dispatchSync($session, $session->zoom_meeting_id ? 'update' : 'create');
        $error = $session->fresh()->zoom_error;

        return back()->with($error ? 'error' : 'success', $error ?: 'Zoom meeting is ready.');
    }

    /** Mentor/admin marks or corrects one student's attendance by hand. */
    public function mark(Request $request, ClassSession $session, User $user): RedirectResponse
    {
        $this->authorizeView($request->user(), $session);
        $data = $request->validate(['status' => ['required', Rule::in(['present', 'partial', 'absent'])]]);
        abort_unless($session->expectedStudents()->whereKey($user->id)->exists(), 404);

        Attendance::updateOrCreate(
            ['class_session_id' => $session->id, 'user_id' => $user->id],
            ['status' => $data['status'], 'source' => 'manual', 'marked_by' => $request->user()->id],
        );

        return back()->with('success', 'Attendance saved.');
    }

    public function export(Request $request, ClassSession $session): StreamedResponse
    {
        $this->authorizeView($request->user(), $session);
        $attendance = Attendance::where('class_session_id', $session->id)->get()->keyBy('user_id');
        $students = $session->expectedStudents()->orderBy('name')->get();
        $name = 'attendance-'.$session->localStart()->format('Y-m-d').'-'.$session->id.'.csv';

        return response()->streamDownload(function () use ($students, $attendance) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student ID', 'Name', 'Phone', 'Status', 'Minutes', 'Source']);
            foreach ($students as $u) {
                $a = $attendance->get($u->id);
                fputcsv($out, [$u->student_code, $u->name, Phone::display($u->phone), $a?->status ?? 'not marked', $a?->minutes, $a?->source]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function generateNotes(Request $request, ClassSession $session, ClassNotesAi $ai): RedirectResponse
    {
        $this->authorizeView($request->user(), $session);
        $language = $request->validate(['language' => ['required', Rule::in(['english', 'roman_urdu', 'urdu'])]])['language'];
        try {
            $ai->generate($session->load('course'), $language, $request->user());
        } catch (AiUnavailable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AI notes are ready. Check them, then publish to students.');
    }

    public function saveNotes(Request $request, ClassSession $session): RedirectResponse
    {
        $this->authorizeView($request->user(), $session);
        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:10000'],
            'key_points' => ['array', 'max:30'],
            'key_points.*' => ['nullable', 'string', 'max:1000'],
            'action_items' => ['array', 'max:30'],
            'action_items.*' => ['nullable', 'string', 'max:1000'],
            'recap' => ['nullable', 'string', 'max:5000'],
            'publish' => ['boolean'],
        ]);

        $session->update([
            'ai_notes' => [
                ...($session->ai_notes ?? []),
                'summary' => $data['summary'] ?? '',
                'key_points' => array_values(array_filter($data['key_points'] ?? [])),
                'action_items' => array_values(array_filter($data['action_items'] ?? [])),
                'recap' => $data['recap'] ?? '',
            ],
            'notes_published' => $data['publish'] ?? false,
        ]);

        return back()->with('success', ($data['publish'] ?? false) ? 'Notes published to students.' : 'Notes saved (not visible to students yet).');
    }

    // ------------------------------------------------------------------

    private function authorizeView(User $user, ClassSession $session): void
    {
        abort_unless($user->isStaff() || $session->mentor_id === $user->id, 403);
    }

    private function row(ClassSession $s, string $tz): array
    {
        $local = $s->starts_at->setTimezone($tz);

        return [
            'id' => $s->id,
            'title' => $s->title,
            'course' => $s->course?->title,
            'batch' => $s->batch?->name,
            'batch_id' => $s->batch_id,
            'mentor' => $s->mentor?->name,
            'zoom_account' => $s->zoomAccount?->name,
            'kind' => $s->kind,
            'status' => $s->status,
            'date' => $local->format('Y-m-d'),
            'time' => $local->format('H:i'),
            'label' => $local->format('D d M Y, g:i A'),
            'duration' => $s->duration_minutes,
            'zoom_ready' => (bool) $s->zoom_meeting_id,
            'zoom_error' => $s->zoom_error,
            'has_notes' => ! empty($s->ai_notes),
            'notes_published' => $s->notes_published,
        ];
    }
}
