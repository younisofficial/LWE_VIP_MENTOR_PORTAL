<?php

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\WeeklyReport;
use App\Services\ReportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
    Notification::fake();
    $this->admin = lweUser(User::ROLE_ADMIN);
    $this->course = lweCourse(20000);
    $this->batch = lweBatch($this->course);
    $this->student = lweUser();
    $this->enrollment = Enrollment::create(['user_id' => $this->student->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'status' => 'active']);
    Payment::create(['enrollment_id' => $this->enrollment->id, 'user_id' => $this->student->id, 'amount' => 20000, 'method' => 'jazzcash', 'status' => 'approved', 'reviewed_at' => now()]);
    $s = ClassSession::create(['course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'title' => 'C', 'starts_at' => now()->subDay(), 'duration_minutes' => 60, 'status' => 'ended']);
    Attendance::create(['class_session_id' => $s->id, 'user_id' => $this->student->id, 'status' => 'partial', 'minutes' => 30]);
});

it('counts revenue, enrollments and attendance for a period', function () {
    $reports = app(ReportService::class);
    [$from, $to] = $reports->range('30d');
    $k = $reports->kpis($from, $to);

    expect($k['revenue'])->toBe(20000.0)
        ->and($k['enrollments'])->toBe(1)
        ->and($k['attendance'])->toBe(50.0)
        ->and(collect($reports->revenueByMonth())->last()['value'])->toEqual(20000)
        ->and($reports->paymentMethods($from, $to)[0]['label'])->toBe('JazzCash');
});

it('opens every report tab and exports CSV for admins only', function () {
    foreach (['overview', 'revenue', 'courses', 'mentors', 'franchise'] as $tab) {
        lweAs($this, $this->admin)->get(route('admin.reports.index', ['tab' => $tab, 'range' => 'month']))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Reports/Index')->where('tab', $tab));
    }
    lweAs($this, $this->admin)->get(route('admin.reports.export', ['tab' => 'courses']))->assertOk();
    lweAs($this, $this->admin)->get(route('admin.dashboard'))->assertOk();

    lweAs($this, lweUser(User::ROLE_MENTOR))->get(route('admin.reports.index'))->assertForbidden();
});

it('lets AI read the report and keeps the answer for the day', function () {
    config(['lwe.ai.api_key' => 'test-key']);
    Setting::put('ai_enabled', true);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'tool_use', 'id' => 't', 'name' => 'save_insights', 'input' => ['headline' => 'Revenue PKR 20,000', 'insights' => ['One course sold'], 'actions' => ['Call pending students']]]],
        'stop_reason' => 'tool_use', 'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
    ])]);

    lweAs($this, $this->admin)->postJson(route('admin.reports.ai', ['range' => 'month']), ['language' => 'english'])
        ->assertOk()->assertJson(['headline' => 'Revenue PKR 20,000']);

    lweAs($this, $this->admin)->get(route('admin.reports.index', ['range' => 'month']))
        ->assertInertia(fn ($p) => $p->where('ai.headline', 'Revenue PKR 20,000'));
});

it('emails the weekly report to admins even when AI is off', function () {
    Setting::put('ai_enabled', false);

    $this->artisan('lwe:weekly-report')->assertSuccessful();

    Notification::assertSentTo($this->admin, WeeklyReport::class, fn ($n) => $n->ai === null && $n->kpis['revenue'] === 20000.0);
});

it('shows a franchise partner only their own students', function () {
    lweAs($this, $this->admin)->post(route('admin.franchise.store'), ['name' => 'Multan Partner', 'phone' => '03451234567', 'password' => 'partner123', 'city' => 'Multan'])
        ->assertSessionHasNoErrors();
    $partner = User::where('phone', '923451234567')->firstOrFail();
    expect($partner->homeRoute())->toBe('partner.dashboard');

    lweAs($this, $this->admin)->post(route('admin.enrollments.store'), [
        'name' => 'Partner Student', 'phone' => '03001112233', 'batch_id' => $this->batch->id, 'source' => 'franchise', 'franchiser_id' => $partner->id, 'activate_now' => true,
    ])->assertSessionHasNoErrors();

    lweAs($this, $partner)->get(route('partner.dashboard'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('Partner/Dashboard')->where('stats.students', 1)->has('rows.data', 1)->where('rows.data.0.student.name', 'Partner Student'));
    lweAs($this, $partner)->get(route('admin.dashboard'))->assertForbidden();

    lweAs($this, $this->admin)->get(route('admin.franchise.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('partners.0.students', 1));
});

it('shows the mentor what is waiting for them', function () {
    $mentor = lweUser(User::ROLE_MENTOR);
    $this->course->mentors()->attach($mentor);
    $this->enrollment->update(['progress_status' => 'at_risk', 'progress_notes' => ['Attendance 50% (needs 75%)']]);

    lweAs($this, $mentor)->get(route('mentor.dashboard'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('todo.at_risk.0.name', $this->student->name));
});
