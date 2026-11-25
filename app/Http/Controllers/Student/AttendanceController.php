<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DailyCheckin;
use App\Models\Setting;
use App\Services\CampusQr;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Daily attendance: "I'm present today" (online) and campus QR scan. */
class AttendanceController extends Controller
{
    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('lwe.timezone'));
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = $this->today();
        $from = $today->subDays(29);
        $marks = DailyCheckin::where('user_id', $user->id)->whereDate('date', '>=', $from->toDateString())
            ->orderBy('date')->get()->groupBy(fn ($c) => $c->date->format('Y-m-d'));

        // Last 30 days for the little calendar.
        $days = collect(range(0, 29))->map(function ($i) use ($from, $marks) {
            $d = $from->addDays($i);
            $m = $marks->get($d->format('Y-m-d'), collect());

            return ['date' => $d->format('Y-m-d'), 'label' => $d->format('D d M'), 'day' => $d->format('j'),
                'self' => $m->contains('mode', 'self'), 'campus' => $m->contains('mode', 'campus')];
        });

        // Streak: days in a row (up to today/yesterday) with any check-in.
        $streak = 0;
        $cursor = $marks->has($today->format('Y-m-d')) ? $today : $today->subDay();
        $all = DailyCheckin::where('user_id', $user->id)->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->unique()->flip();
        while ($all->has($cursor->format('Y-m-d'))) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        $todays = $marks->get($today->format('Y-m-d'), collect());

        return Inertia::render('Student/Attendance/Index', [
            'today' => $today->format('l, d M Y'),
            'marked' => $todays->firstWhere('mode', 'self')?->only('note'),
            'campus_today' => $todays->contains('mode', 'campus'),
            'note_required' => (bool) Setting::get('checkin_note_required'),
            'days' => $days,
            'streak' => $streak,
            'this_month' => DailyCheckin::where('user_id', $user->id)->whereDate('date', '>=', $today->startOfMonth()->toDateString())->distinct()->count('date'),
            'courses' => $user->enrollments()->whereIn('status', ['active', 'completed'])->with('course:id,title')->get()->pluck('course')->unique('id')->values()->map->only('id', 'title'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'note' => [Setting::get('checkin_note_required') ? 'required' : 'nullable', 'string', 'min:10', 'max:500'],
            'course_id' => ['nullable', 'integer'],
        ], [
            'note.required' => 'Write one or two lines about what you learned or practised today.',
            'note.min' => 'Write a little more (at least 10 letters).',
        ]);
        $courseId = isset($data['course_id']) && in_array((int) $data['course_id'], $user->learningCourseIds(), true) ? (int) $data['course_id'] : null;

        try {
            DailyCheckin::create([
                'user_id' => $user->id, 'date' => $this->today()->toDateString(), 'mode' => 'self',
                'course_id' => $courseId, 'note' => $data['note'] ?? null, 'ip' => $request->ip(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'You have already marked today. See you tomorrow!');
        }

        return back()->with('success', 'Present for today ✔ Keep your streak going!');
    }

    /** Opened by scanning the campus QR with the phone camera. */
    public function campus(Request $request, CampusQr $qr): RedirectResponse
    {
        $user = $request->user();
        if (! $qr->isValid($request->query('t'))) {
            return redirect()->route('student.attendance.index')->with('error', 'This QR code has expired. Scan the code on the campus screen again.');
        }

        try {
            DailyCheckin::create([
                'user_id' => $user->id, 'date' => $this->today()->toDateString(), 'mode' => 'campus', 'ip' => $request->ip(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('student.attendance.index')->with('success', 'Your campus attendance is already marked today.');
        }

        return redirect()->route('student.attendance.index')->with('success', 'Campus attendance marked ✔ Welcome to '.Setting::get('campus_name').'!');
    }
}
