<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $students = User::role(User::ROLE_STUDENT)
            ->withCount(['enrollments as active_courses' => fn ($q) => $q->where('status', 'active')])
            ->when($request->search, function ($q, $s) {
                $phone = Phone::normalize($s);
                $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('student_code', 'like', "%{$s}%")
                    ->when($phone, fn ($w) => $w->orWhere('phone', $phone)));
            })
            ->when($request->status === 'flagged', fn ($q) => $q->whereNotNull('flagged_at'))
            ->when($request->status === 'suspended', fn ($q) => $q->where('status', 'suspended'))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'code' => $u->student_code,
                'phone' => Phone::display($u->phone),
                'email' => $u->email,
                'city' => $u->city,
                'status' => $u->status,
                'active_courses' => $u->active_courses,
                'flagged' => (bool) $u->flagged_at,
                'device_locked' => (bool) $u->locked_device_hash,
                'last_login' => $u->last_login_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function show(User $user): Response
    {
        $user->load(['enrollments.course:id,title,slug', 'enrollments.batch:id,name', 'payments.enrollment.course:id,title']);

        return Inertia::render('Admin/Students/Show', [
            'student' => [
                ...$user->only('id', 'name', 'email', 'city', 'status', 'student_code', 'whatsapp_opt_in'),
                'phone' => Phone::display($user->phone),
                'flagged_at' => $user->flagged_at?->diffForHumans(),
                'device_locked' => (bool) $user->locked_device_hash,
                'last_login' => $user->last_login_at?->timezone(config('lwe.timezone'))->format('d M Y, g:i A'),
                'roles' => $user->getRoleNames(),
            ],
            'enrollments' => $user->enrollments->map(fn ($e) => [
                'id' => $e->id,
                'course' => $e->course->title,
                'batch' => $e->batch?->name,
                'status' => $e->status,
                'amount_due' => (float) $e->amount_due,
                'progress' => $e->progressPercent(),
            ]),
            'payments' => $user->payments->sortByDesc('id')->values()->map(fn ($p) => [
                'id' => $p->id,
                'course' => $p->enrollment->course->title,
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'date' => $p->created_at->format('d M Y'),
            ]),
            'logins' => $user->loginLogs()->limit(20)->get()->map(fn ($l) => [
                'device' => $l->device_label,
                'ip' => $l->ip_address,
                'result' => $l->result,
                'time' => $l->created_at->timezone(config('lwe.timezone'))->format('d M Y, g:i A'),
            ]),
        ]);
    }

    /** Suspend / activate, lock to the last device, unlock, force logout, clear the flag. */
    public function action(Request $request, User $user): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'activate', 'lock_device', 'unlock_device', 'force_logout', 'clear_flag'])],
        ])['action'];

        switch ($action) {
            case 'suspend':
                $user->update(['status' => 'suspended']);
                $user->forceFill(['current_session_id' => null])->save();
                break;
            case 'activate':
                $user->update(['status' => 'active']);
                break;
            case 'lock_device':
                $last = $user->loginLogs()->where('result', 'success')->first();
                if (! $last) {
                    return back()->with('error', 'This student has not logged in yet.');
                }
                $user->forceFill(['locked_device_hash' => $last->device_hash])->save();
                break;
            case 'unlock_device':
                $user->forceFill(['locked_device_hash' => null])->save();
                break;
            case 'force_logout':
                // A random value that no browser session has → every device is logged out.
                $user->forceFill(['current_session_id' => 'forced-'.bin2hex(random_bytes(8))])->save();
                break;
            case 'clear_flag':
                $user->forceFill(['flagged_at' => null])->save();
                break;
        }

        activity('users')->causedBy($request->user())->performedOn($user)->log("student.{$action}");

        return back()->with('success', 'Done.');
    }
}
