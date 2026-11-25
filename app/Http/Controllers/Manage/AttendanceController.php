<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\DailyCheckin;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use App\Services\CampusQr;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Daily attendance report (self check-in + campus QR) and the campus QR screen. */
class AttendanceController extends Controller
{
    use ManagesCourses;

    private function students(Request $request)
    {
        $ids = $request->user()->manageableCourseIds();
        $enrolled = Enrollment::whereIn('status', ['active', 'completed'])
            ->when($ids !== null, fn ($q) => $q->whereIn('course_id', $ids))
            ->when($request->course_id, fn ($q, $c) => $q->where('course_id', $c))
            ->when($request->batch_id, fn ($q, $b) => $q->where('batch_id', $b))
            ->select('user_id');

        return User::whereIn('id', $enrolled)
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('student_code', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->orderBy('name');
    }

    private function date(Request $request): CarbonImmutable
    {
        $tz = config('lwe.timezone');
        try {
            return $request->filled('date') ? CarbonImmutable::parse($request->date, $tz) : CarbonImmutable::now($tz);
        } catch (\Throwable) {
            return CarbonImmutable::now($tz);
        }
    }

    public function index(Request $request): Response
    {
        $date = $this->date($request);
        $students = $this->students($request)->paginate(50, ['id', 'name', 'student_code', 'phone'])->withQueryString();
        $marks = DailyCheckin::whereDate('date', $date->toDateString())
            ->whereIn('user_id', $students->pluck('id'))->get()->groupBy('user_id');

        $rows = $students->through(fn (User $u) => [
            ...$u->only('id', 'name', 'student_code', 'phone'),
            'self' => ($s = $marks->get($u->id)?->firstWhere('mode', 'self')) ? ['time' => $s->created_at->setTimezone(config('lwe.timezone'))->format('g:i A'), 'note' => $s->note] : null,
            'campus' => ($c = $marks->get($u->id)?->firstWhere('mode', 'campus')) ? ['time' => $c->created_at->setTimezone(config('lwe.timezone'))->format('g:i A')] : null,
        ]);

        $all = (clone $this->students($request))->pluck('id');
        $day = DailyCheckin::whereDate('date', $date->toDateString())->whereIn('user_id', $all);

        return Inertia::render('Manage/Attendance/Index', [
            'rows' => $rows,
            'date' => $date->toDateString(),
            'filters' => $request->only('course_id', 'batch_id', 'search'),
            'stats' => [
                'students' => $all->count(),
                'self' => (clone $day)->where('mode', 'self')->count(),
                'campus' => (clone $day)->where('mode', 'campus')->count(),
                'any' => (clone $day)->distinct()->count('user_id'),
            ],
            ...$this->courseOptions($request->user()),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $date = $this->date($request);
        $students = $this->students($request)->get(['id', 'name', 'student_code', 'phone']);
        $marks = DailyCheckin::whereDate('date', $date->toDateString())->whereIn('user_id', $students->pluck('id'))->get()->groupBy('user_id');

        return response()->streamDownload(function () use ($students, $marks) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student ID', 'Name', 'Phone', 'Self check-in', 'What I learned', 'Campus QR']);
            foreach ($students as $u) {
                $m = $marks->get($u->id, collect());
                $self = $m->firstWhere('mode', 'self');
                $campus = $m->firstWhere('mode', 'campus');
                fputcsv($out, [
                    $u->student_code, $u->name, $u->phone,
                    $self ? $self->created_at->setTimezone(config('lwe.timezone'))->format('g:i A') : '',
                    $self?->note ?? '',
                    $campus ? $campus->created_at->setTimezone(config('lwe.timezone'))->format('g:i A') : '',
                ]);
            }
            fclose($out);
        }, 'daily-attendance-'.$date->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Full-screen QR for the campus reception screen / projector. */
    public function campusScreen(): Response
    {
        return Inertia::render('Manage/Attendance/CampusQr', [
            'campus' => Setting::get('campus_name'),
            'seconds' => app(CampusQr::class)->seconds(),
        ]);
    }

    /** The screen asks for a fresh code before the old one runs out. */
    public function campusToken(CampusQr $qr): JsonResponse
    {
        $now = $qr->current();
        $today = CarbonImmutable::now(config('lwe.timezone'))->toDateString();

        return response()->json([
            'url' => route('student.attendance.campus', ['t' => $now['token']]),
            'expires_in' => $now['expires_in'],
            'today_count' => DailyCheckin::whereDate('date', $today)->where('mode', 'campus')->count(),
        ]);
    }
}
