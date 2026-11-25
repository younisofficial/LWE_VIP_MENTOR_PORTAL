<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CouponService;
use App\Services\EnrollmentService;
use App\Services\Phone;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    public function index(Request $request): Response
    {
        $enrollments = Enrollment::query()
            ->with(['user:id,name,phone,student_code', 'course:id,title', 'batch:id,name'])
            ->when($request->course_id, fn ($q, $id) => $q->where('course_id', $id))
            ->when($request->batch_id, fn ($q, $id) => $q->where('batch_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $s) {
                $phone = Phone::normalize($s);
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")
                    ->orWhere('student_code', 'like', "%{$s}%")
                    ->when($phone, fn ($u) => $u->orWhere('phone', $phone)));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Enrollment $e) => [
                'id' => $e->id,
                'student' => $e->user->name,
                'student_id' => $e->user->id,
                'code' => $e->user->student_code,
                'phone' => Phone::display($e->user->phone),
                'course' => $e->course->title,
                'batch' => $e->batch?->name,
                'batch_id' => $e->batch_id,
                'course_id' => $e->course_id,
                'status' => $e->status,
                'source' => $e->source,
                'amount_due' => (float) $e->amount_due,
                'date' => $e->created_at->format('d M Y'),
            ]);

        return Inertia::render('Admin/Enrollments/Index', [
            'enrollments' => $enrollments,
            'filters' => $request->only('course_id', 'batch_id', 'status', 'search'),
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'franchisers' => User::role(User::ROLE_FRANCHISER)->orderBy('name')->get(['id', 'name', 'city']),
            'batches' => Batch::with('courses:id,title')->whereIn('status', ['upcoming', 'running'])->orderBy('name')->get(['id', 'name', 'course_id'])
                ->map(fn (Batch $b) => ['id' => $b->id, 'name' => $b->name, 'courses' => $b->courses->map->only('id', 'title')->values()]),
            'statuses' => Enrollment::STATUSES,
            'importReport' => session('importReport'),
        ]);
    }

    /** Manual enroll: find the student by phone (or create them), then enroll. */
    public function store(Request $request, EnrollmentService $service, CouponService $coupons): RedirectResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^923\d{9}$/'],
            'email' => ['nullable', 'email'],
            'batch_id' => ['required', 'exists:batches,id'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer'],
            'activate_now' => ['boolean'],
            'source' => ['required', Rule::in(['admin', 'scholarship', 'franchise'])],
            'franchiser_id' => ['nullable', 'integer', 'exists:users,id'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ], ['phone.regex' => 'Enter a valid Pakistani mobile number.']);

        $batch = Batch::findOrFail($data['batch_id']);
        $courses = $batch->courses()->when($data['course_ids'] ?? null, fn ($q, $ids) => $q->whereKey($ids))->get();
        if ($courses->isEmpty()) {
            throw ValidationException::withMessages(['course_ids' => 'Choose at least one course of this batch.']);
        }

        $user = User::where('phone', $data['phone'])->first();
        if (! $user) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => ($data['email'] ?? null) && ! User::where('email', $data['email'])->exists() ? $data['email'] : null,
                'password' => Hash::make(Str::random(32)),
            ]);
            $user->assignRole(User::ROLE_STUDENT);
            $user->notify(new \App\Notifications\WelcomeStudent(setPassword: true));
        }

        $done = [];
        $problems = [];
        foreach ($courses as $course) {
            // A coupon is used only on the course(s) it is valid for.
            $coupon = $data['coupon'] ?? null;
            if ($coupon) {
                try {
                    $coupons->findValid($coupon, $course);
                } catch (ValidationException $e) {
                    if ($courses->count() === 1) {
                        throw $e;
                    }
                    $coupon = null;
                }
            }
            try {
                $made = $service->enroll($user, $course, $batch, $coupon, $data['source'], $request->user(), $data['activate_now'] ?? false);
                if (! empty($data['franchiser_id'])) {
                    $made->forceFill(['franchiser_id' => $data['franchiser_id']])->saveQuietly();
                }
                $done[] = $course->title;
            } catch (ValidationException $e) {
                if ($courses->count() === 1) {
                    throw $e;
                }
                $problems[] = $course->title.': '.collect($e->errors())->flatten()->first();
            }
        }

        if (! $done) {
            throw ValidationException::withMessages(['course_ids' => implode(' · ', $problems)]);
        }

        $msg = "{$user->name} enrolled in ".implode(', ', $done).'.';

        $back = back()->with('success', $msg);

        return $problems ? $back->with('error', 'Skipped — '.implode(' · ', $problems)) : $back;
    }

    public function import(Request $request, StudentImportService $importer): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'batch_id' => ['required', 'exists:batches,id'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer'],
            'source' => ['required', Rule::in(['csv', 'scholarship', 'franchise'])],
            'franchiser_id' => ['nullable', 'integer', 'exists:users,id'],
            'activate_now' => ['boolean'],
        ]);

        $report = $importer->import(
            $request->file('file'),
            Batch::findOrFail($data['batch_id']),
            $request->user(),
            $data['source'],
            $data['activate_now'] ?? false,
            $data['course_ids'] ?? null,
            $data['franchiser_id'] ?? null,
        );

        return back()
            ->with('importReport', $report)
            ->with('success', "Import done: {$report['enrolled']} enrolled, {$report['created']} new students, {$report['skipped']} skipped.");
    }

    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Enrollment::STATUSES)],
            'batch_id' => ['nullable', Rule::exists('batch_course', 'batch_id')->where('course_id', $enrollment->course_id)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] === 'active' && ! $enrollment->activated_at) {
            $data['activated_at'] = now();
        }
        $enrollment->update($data);

        return back()->with('success', 'Enrollment updated.');
    }

    /** CSV template download so admins use the right columns. */
    public function template()
    {
        return response("name,phone,email,city\nAli Khan,03001234567,ali@example.com,Multan\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lwe-students-template.csv"',
        ]);
    }
}
