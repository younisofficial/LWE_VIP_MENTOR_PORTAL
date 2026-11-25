<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\ReportAi;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reports: overview, revenue, courses & batches, mentors, franchise — with CSV and AI notes. */
class ReportController extends Controller
{
    public const TABS = ['overview', 'revenue', 'courses', 'mentors', 'franchise'];

    private function period(Request $request, ReportService $reports): array
    {
        return $reports->range($request->input('range'), $request->input('from'), $request->input('to'));
    }

    public function index(Request $request, ReportService $reports): Response
    {
        $tab = in_array($request->tab, self::TABS, true) ? $request->tab : 'overview';
        [$from, $to, $label] = $this->period($request, $reports);

        $data = match ($tab) {
            'overview' => [
                'kpis' => $reports->kpis($from, $to),
                'revenueByMonth' => $reports->revenueByMonth(),
                'enrollmentsByMonth' => $reports->enrollmentsByMonth(),
                'attendanceByWeek' => $reports->attendanceByWeek(),
                'checkinsByDay' => $reports->checkinsByDay(),
                'sources' => $reports->sources($from, $to),
            ],
            'revenue' => [
                'kpis' => $reports->kpis($from, $to),
                'revenueByMonth' => $reports->revenueByMonth(),
                'methods' => $reports->paymentMethods($from, $to),
                'courses' => $reports->courses($from, $to),
            ],
            'courses' => ['courses' => $reports->courses($from, $to), 'batches' => $reports->batches()],
            'mentors' => ['mentors' => $reports->mentors($from, $to)],
            'franchise' => ['franchise' => $reports->franchise($from, $to)],
        };

        $tz = config('lwe.timezone');

        return Inertia::render('Admin/Reports/Index', [
            'tab' => $tab,
            'filters' => [
                'range' => $request->input('range', '30d'),
                'from' => $from->setTimezone($tz)->toDateString(),
                'to' => $to->setTimezone($tz)->toDateString(),
                'label' => $label,
            ],
            'data' => $data,
            'ai' => Cache::get($this->aiKey($request)),
        ]);
    }

    private function aiKey(Request $request): string
    {
        return 'report-ai:'.md5(json_encode([$request->input('range', '30d'), $request->input('from'), $request->input('to'), now()->toDateString()]));
    }

    /** AI notes for the chosen period (kept for the day so it is not asked twice). */
    public function ai(Request $request, ReportService $reports, ReportAi $ai): JsonResponse
    {
        $language = $request->validate(['language' => ['required', Rule::in(['english', 'roman_urdu', 'urdu'])]])['language'];
        [$from, $to, $label] = $this->period($request, $reports);

        try {
            $notes = $ai->insights($reports->digest($from, $to, $label), $language, $request->user());
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        Cache::put($this->aiKey($request), $notes, now()->endOfDay());

        return response()->json($notes);
    }

    public function export(Request $request, ReportService $reports): StreamedResponse
    {
        $tab = in_array($request->tab, self::TABS, true) ? $request->tab : 'courses';
        [$from, $to] = $this->period($request, $reports);

        [$head, $rows] = match ($tab) {
            'mentors' => [['Mentor', 'Students', 'Classes held', 'Attendance %', 'Tasks checked', 'Median check time (h)', 'Waiting to check', 'Q&A answers', 'Unanswered questions', 'Avg score'],
                collect($reports->mentors($from, $to))->map(fn ($m) => [$m['name'], $m['students'], $m['classes'], $m['attendance'], $m['graded'], $m['check_hours'], $m['waiting'], $m['answers'], $m['unanswered'], $m['avg_score']])],
            'franchise' => [['Partner', 'City', 'Students', 'New in period', 'Active', 'Payment pending', 'Completed', 'Revenue (PKR)'],
                collect($reports->franchise($from, $to))->map(fn ($f) => [$f['name'], $f['city'], $f['students'], $f['new'], $f['active'], $f['pending'], $f['completed'], $f['revenue']])],
            'revenue' => [['Month', 'Revenue (PKR)'], collect($reports->revenueByMonth())->map(fn ($r) => [$r['label'], $r['value']])],
            default => [['Course', 'Active students', 'New in period', 'Revenue (PKR)', 'Attendance %', 'Avg score', 'At risk', 'Completed'],
                collect($reports->courses($from, $to))->map(fn ($c) => [$c['title'], $c['active'], $c['new'], $c['revenue'], $c['attendance'], $c['avg_score'], $c['at_risk'], $c['completed']])],
        };

        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $head);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, "report-{$tab}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
