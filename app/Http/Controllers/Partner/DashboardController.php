<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\Phone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Franchise partner panel: only the students they brought, read-only. */
class DashboardController extends Controller
{
    private function query(Request $request)
    {
        return Enrollment::with(['user:id,name,student_code,phone,city', 'course:id,title', 'batch:id,name', 'certificate'])
            ->where('franchiser_id', $request->user()->id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->whereHas('user', fn ($u) => $u->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('student_code', 'like', "%{$s}%"))))
            ->latest();
    }

    public function __invoke(Request $request): Response
    {
        $mine = Enrollment::where('franchiser_id', $request->user()->id);

        return Inertia::render('Partner/Dashboard', [
            'stats' => [
                'students' => (clone $mine)->distinct()->count('user_id'),
                'active' => (clone $mine)->where('status', 'active')->count(),
                'pending' => (clone $mine)->where('status', 'pending_payment')->count(),
                'completed' => (clone $mine)->where('status', 'completed')->count(),
                'at_risk' => (clone $mine)->where('status', 'active')->where('progress_status', 'at_risk')->count(),
                'paid_month' => (float) Payment::where('status', 'approved')->where('reviewed_at', '>=', now(config('lwe.timezone'))->startOfMonth()->utc())
                    ->whereIn('enrollment_id', (clone $mine)->select('id'))->sum('amount'),
            ],
            'rows' => $this->query($request)->paginate(30)->withQueryString()->through(fn (Enrollment $e) => [
                'id' => $e->id,
                'student' => [...$e->user->only('name', 'student_code', 'city'), 'phone' => Phone::display($e->user->phone)],
                'course' => $e->course->title,
                'batch' => $e->batch?->name,
                'status' => $e->status,
                'progress_status' => $e->progress_status,
                'attendance' => $e->attendance_percent,
                'score' => $e->score,
                'certificate' => $e->certificate?->isValid() ? route('certificates.show', $e->certificate->code) : null,
                'joined' => $e->created_at->format('d M Y'),
            ]),
            'filters' => $request->only('status', 'search'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student ID', 'Name', 'Phone', 'City', 'Course', 'Batch', 'Status', 'Attendance %', 'Score', 'Joined']);
            foreach ($rows as $e) {
                fputcsv($out, [$e->user->student_code, $e->user->name, $e->user->phone, $e->user->city, $e->course->title, $e->batch?->name, $e->status, $e->attendance_percent, $e->score, $e->created_at->format('Y-m-d')]);
            }
            fclose($out);
        }, 'my-students-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
