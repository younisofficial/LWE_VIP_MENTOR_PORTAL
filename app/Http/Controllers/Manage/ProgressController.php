<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CertificateService;
use App\Services\ProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Results & certificates: every student's attendance / tasks / quiz / score, at-risk list, certificates. */
class ProgressController extends Controller
{
    use ManagesCourses;

    private function query(Request $request): Builder
    {
        return $this->scopeToUser(Enrollment::query(), $request->user())
            ->with(['user:id,name,student_code,phone', 'course:id,title', 'batch:id,name', 'certificate'])
            ->whereIn('status', ['active', 'completed'])
            ->when($request->course_id, fn ($q, $c) => $q->where('course_id', $c))
            ->when($request->batch_id, fn ($q, $b) => $q->where('batch_id', $b))
            ->when($request->state === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when(in_array($request->state, ['at_risk', 'eligible', 'on_track'], true), fn ($q) => $q->where('status', 'active')->where('progress_status', $request->state))
            ->when($request->search, fn ($q, $s) => $q->whereHas('user', fn ($u) => $u->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('student_code', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))));
    }

    public function index(Request $request): Response
    {
        $base = $this->scopeToUser(Enrollment::query(), $request->user())->whereIn('status', ['active', 'completed'])
            ->when($request->course_id, fn ($q, $c) => $q->where('course_id', $c))
            ->when($request->batch_id, fn ($q, $b) => $q->where('batch_id', $b));

        $rows = $this->query($request)->orderByRaw("CASE progress_status WHEN 'at_risk' THEN 0 WHEN 'eligible' THEN 1 ELSE 2 END")
            ->orderByDesc('score')->paginate(30)->withQueryString()
            ->through(fn (Enrollment $e) => [
                'id' => $e->id,
                'student' => $e->user->only('id', 'name', 'student_code', 'phone'),
                'course' => $e->course->title,
                'batch' => $e->batch?->name,
                'status' => $e->status,
                'progress_status' => $e->progress_status,
                'attendance' => $e->attendance_percent,
                'tasks' => $e->tasks_percent,
                'quiz' => $e->quiz_percent,
                'score' => $e->score,
                'notes' => $e->progress_notes ?? [],
                'updated' => $e->progress_updated_at?->diffForHumans(),
                'certificate' => $e->certificate ? [
                    'id' => $e->certificate->id, 'code' => $e->certificate->code, 'grade' => $e->certificate->grade,
                    'valid' => $e->certificate->isValid(), 'url' => route('certificates.show', $e->certificate->code),
                ] : null,
            ]);

        return Inertia::render('Manage/Progress/Index', [
            'rows' => $rows,
            'filters' => $request->only('course_id', 'batch_id', 'state', 'search'),
            'stats' => [
                'students' => (clone $base)->count(),
                'at_risk' => (clone $base)->where('status', 'active')->where('progress_status', 'at_risk')->count(),
                'eligible' => (clone $base)->where('status', 'active')->where('progress_status', 'eligible')->count(),
                'certified' => (clone $base)->where('status', 'completed')->count(),
                'average' => round((float) (clone $base)->avg('score'), 1),
            ],
            ...$this->courseOptions($request->user()),
        ]);
    }

    /** Recalculate now (instead of waiting for the 7 AM run). */
    public function recalculate(Request $request, ProgressService $progress): RedirectResponse
    {
        $count = 0;
        $this->query($request)->where('status', 'active')->chunkById(200, function ($chunk) use ($progress, &$count) {
            $chunk->each(function ($e) use ($progress, &$count) {
                $progress->refresh($e);
                $count++;
            });
        });

        return back()->with('success', "Results updated for {$count} students.");
    }

    public function issue(Request $request, Enrollment $enrollment, CertificateService $certificates, ProgressService $progress): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $enrollment->course_id);
        $r = $progress->refresh($enrollment->load('course'));
        // Not eligible yet: only an admin may override, and must say so.
        if (! $r['eligible'] && ! ($request->user()->isStaff() && $request->boolean('override'))) {
            return back()->with('error', 'Not eligible yet: '.implode(' · ', $r['notes']).'. An admin can issue it anyway.');
        }
        $cert = $certificates->issue($enrollment, $request->user());

        return back()->with('success', "Certificate {$cert->code} issued. The student was told.");
    }

    public function revoke(Request $request, Certificate $certificate, CertificateService $certificates): RedirectResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];
        $certificates->revoke($certificate, $reason, $request->user());

        return back()->with('success', "Certificate {$certificate->code} revoked. The verify page now shows it as not valid.");
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student ID', 'Name', 'Phone', 'Course', 'Batch', 'Attendance %', 'Tasks %', 'Quiz %', 'Score', 'Result', 'Certificate', 'Notes']);
            foreach ($rows as $e) {
                fputcsv($out, [
                    $e->user->student_code, $e->user->name, $e->user->phone, $e->course->title, $e->batch?->name,
                    $e->attendance_percent, $e->tasks_percent, $e->quiz_percent, $e->score,
                    $e->status === 'completed' ? 'completed' : $e->progress_status,
                    $e->certificate?->code, implode(' · ', $e->progress_notes ?? []),
                ]);
            }
            fclose($out);
        }, 'results-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
