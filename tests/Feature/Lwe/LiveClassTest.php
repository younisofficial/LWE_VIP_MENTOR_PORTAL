<?php

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use App\Models\ZoomAccount;
use App\Notifications\ClassChanged;
use App\Notifications\ClassReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
    Notification::fake();
    Http::preventStrayRequests();
    config(['lwe.ai.api_key' => null]);
    $this->admin = lweUser(User::ROLE_ADMIN);
    $this->mentor = lweUser(User::ROLE_MENTOR);
    $this->course = lweCourse(null);
    $this->batch = lweBatch($this->course, ['mentor_id' => $this->mentor->id]);
});

it('schedules classes on the chosen days and creates Zoom meetings', function () {
    lweZoomAccount();
    lweFakeZoom();

    lweAs($this, $this->admin)->post(route('admin.classes.store'), [
        'batch_id' => $this->batch->id, 'start_date' => lweNextDay('monday'), 'time' => '20:00', 'duration' => 90,
        'days' => ['mon', 'wed'], 'weeks' => 2,
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $sessions = ClassSession::orderBy('starts_at')->get();
    expect($sessions)->toHaveCount(4)
        ->and($sessions->pluck('zoom_meeting_id')->unique()->all())->toBe(['98765432101'])
        ->and($sessions->first()->localStart()->format('D H:i'))->toBe('Mon 20:00')
        ->and($sessions->first()->mentor_id)->toBe($this->mentor->id);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/users/zoom1%40lwe.test/meetings') && $r['settings']['auto_recording'] === 'cloud');
});

it('never double-books a Zoom account or a mentor', function () {
    lweZoomAccount();
    lweFakeZoom();
    $otherBatch = lweBatch($this->course, ['name' => 'Batch 2']);
    $payload = ['start_date' => lweNextDay('tuesday'), 'time' => '20:00', 'duration' => 60, 'days' => ['tue'], 'weeks' => 1];

    lweAs($this, $this->admin)->post(route('admin.classes.store'), [...$payload, 'batch_id' => $this->batch->id])->assertSessionHasNoErrors();

    // Same time, only one Zoom account → refused
    lweAs($this, $this->admin)->post(route('admin.classes.store'), [...$payload, 'batch_id' => $otherBatch->id, 'mentor_id' => null])
        ->assertSessionHasErrors('zoom');

    // A second account makes it possible, but the same mentor is still busy
    lweZoomAccount('Zoom 2');
    lweAs($this, $this->admin)->post(route('admin.classes.store'), [...$payload, 'batch_id' => $otherBatch->id, 'mentor_id' => $this->mentor->id])
        ->assertSessionHasErrors('mentor_id');
    lweAs($this, $this->admin)->post(route('admin.classes.store'), [...$payload, 'batch_id' => $otherBatch->id])
        ->assertSessionHasNoErrors();

    expect(ClassSession::pluck('zoom_account_id')->unique())->toHaveCount(2);
});

it('opens the class inside the portal only in the join window', function () {
    $account = lweZoomAccount();
    $student = lweUser();
    Enrollment::create(['user_id' => $student->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'status' => 'active']);
    $session = ClassSession::create([
        'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'zoom_account_id' => $account->id, 'title' => 'Product research',
        'starts_at' => now()->addHours(2), 'duration_minutes' => 60, 'zoom_meeting_id' => '98765432101', 'zoom_password' => 'pw1',
    ]);

    lweAs($this, $student)->get(route('student.classes.join', $session))->assertRedirect(route('student.classes.index'));

    $this->travelTo(now()->addHours(2)->subMinutes(3));
    lweAs($this, $student)->get(route('student.classes.join', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Classes/Room')
            ->where('zoom.customerKey', (string) $student->id)
            ->where('zoom.meetingNumber', '98765432101')
            ->where('role', 0)
            ->where('zoom.signature', function ($jwt) {
                [$h, $p, $sig] = explode('.', $jwt);
                $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
                $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", 'sdksecret', true)), '+/', '-_'), '=');

                return hash_equals($expected, $sig) && $payload['appKey'] === 'sdkkey' && $payload['role'] === 0 && $payload['mn'] === '98765432101';
            }));

    // Not enrolled → no entry
    lweAs($this, lweUser())->get(route('student.classes.join', $session))->assertForbidden();
});

it('turns Zoom webhooks into attendance', function () {
    $account = lweZoomAccount();
    [$came, $missed] = [lweUser(), lweUser()];
    foreach ([$came, $missed] as $u) {
        Enrollment::create(['user_id' => $u->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'status' => 'active']);
    }
    $start = CarbonImmutable::parse('2026-12-01 15:00:00', 'UTC');
    $session = ClassSession::create([
        'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'zoom_account_id' => $account->id, 'title' => 'Class',
        'starts_at' => $start, 'duration_minutes' => 100, 'zoom_meeting_id' => '555',
    ]);

    // Wrong signature
    $this->call('POST', '/webhooks/zoom', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_ZM_REQUEST_TIMESTAMP' => (string) time(), 'HTTP_X_ZM_SIGNATURE' => 'v0=bad'], '{}')
        ->assertStatus(401);

    // URL validation handshake
    lweZoomPost($this, ['event' => 'endpoint.url_validation', 'payload' => ['plainToken' => 'abc']])
        ->assertOk()->assertJson(['plainToken' => 'abc', 'encryptedToken' => hash_hmac('sha256', 'abc', 'whsecret')]);

    lweZoomPost($this, ['event' => 'meeting.started', 'payload' => ['object' => ['id' => 555, 'start_time' => $start->toIso8601String()]]]);
    // Joins 10 min late, drops after 30 min, rejoins for 50 min → 80 of 100 minutes = present
    lweZoomPost($this, ['event' => 'meeting.participant_joined', 'payload' => ['object' => ['id' => 555, 'participant' => ['customer_key' => (string) $came->id, 'user_name' => 'X', 'participant_user_id' => 'p1', 'join_time' => $start->addMinutes(10)->toIso8601String()]]]]);
    lweZoomPost($this, ['event' => 'meeting.participant_left', 'payload' => ['object' => ['id' => 555, 'participant' => ['customer_key' => (string) $came->id, 'participant_user_id' => 'p1', 'leave_time' => $start->addMinutes(40)->toIso8601String()]]]]);
    lweZoomPost($this, ['event' => 'meeting.participant_joined', 'payload' => ['object' => ['id' => 555, 'participant' => ['user_name' => 'Name – '.$came->student_code, 'participant_user_id' => 'p1', 'join_time' => $start->addMinutes(50)->toIso8601String()]]]]);
    lweZoomPost($this, ['event' => 'meeting.ended', 'payload' => ['object' => ['id' => 555, 'end_time' => $start->addMinutes(100)->toIso8601String()]]])->assertOk();

    $cameRow = Attendance::where('user_id', $came->id)->first();
    expect($session->fresh()->status)->toBe('ended')
        ->and($cameRow->status)->toBe('present')
        ->and($cameRow->minutes)->toBe(80)
        ->and(Attendance::where('user_id', $missed->id)->first()->status)->toBe('absent');
});

it('sends each reminder once', function () {
    $account = lweZoomAccount();
    $student = lweUser();
    Enrollment::create(['user_id' => $student->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'status' => 'active']);
    ClassSession::create([
        'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'zoom_account_id' => $account->id, 'title' => 'Class',
        'starts_at' => now()->addMinutes(40), 'duration_minutes' => 60, 'zoom_meeting_id' => '1',
    ]);

    $this->artisan('lwe:class-reminders')->assertSuccessful();
    $this->artisan('lwe:class-reminders')->assertSuccessful();

    Notification::assertSentToTimes($student, ClassReminderNotification::class, 1);
    Notification::assertSentTo($student, ClassReminderNotification::class, fn ($n) => $n->kind === 'hour');
});

it('cancels a class, deletes the meeting and tells students', function () {
    $account = lweZoomAccount();
    lweFakeZoom();
    $student = lweUser();
    Enrollment::create(['user_id' => $student->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'status' => 'active']);
    $session = ClassSession::create([
        'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'zoom_account_id' => $account->id, 'title' => 'Class',
        'starts_at' => now()->addDay(), 'duration_minutes' => 60, 'zoom_meeting_id' => '777',
    ]);

    lweAs($this, $this->admin)->post(route('admin.classes.cancel', $session), ['reason' => 'Mentor unwell'])->assertSessionHas('success');

    expect($session->fresh()->status)->toBe('cancelled');
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/meetings/777'));
    Notification::assertSentTo($student, ClassChanged::class, fn ($n) => $n->kind === 'cancelled');
});

it('lets the mentor start the class as host and writes AI notes from the transcript', function () {
    $account = lweZoomAccount();
    lweFakeZoom();
    $session = ClassSession::create([
        'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'mentor_id' => $this->mentor->id, 'zoom_account_id' => $account->id,
        'title' => 'Class', 'starts_at' => now()->addMinutes(10), 'duration_minutes' => 60, 'zoom_meeting_id' => '888',
    ]);

    lweAs($this, $this->mentor)->get(route('host.classes.start', $session))
        ->assertOk()->assertInertia(fn ($page) => $page->component('Classes/Room')->where('role', 1)->where('zoom.zak', 'zak123'));

    // Other mentors cannot start it
    lweAs($this, lweUser(User::ROLE_MENTOR))->get(route('host.classes.start', $session))->assertForbidden();

    // AI notes
    config(['lwe.ai.api_key' => 'test-key']);
    Setting::put('ai_enabled', true);
    $session->update(['transcript' => "Mentor: Aaj hum product research seekhenge.\nStudent: Kya Daraz par bhi?"]);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'tool_use', 'id' => 't', 'name' => 'save_class_notes', 'input' => [
            'summary' => 'Product research ki class.', 'key_points' => ['Demand check karein'], 'action_items' => ['3 products dhoondein'], 'recap' => 'Recap',
        ]]],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
    ])]);

    lweAs($this, $this->mentor)->post(route('mentor.classes.notes.ai', $session), ['language' => 'roman_urdu'])->assertSessionHas('success');
    expect($session->fresh()->ai_notes['action_items'])->toBe(['3 products dhoondein'])->and($session->fresh()->notes_published)->toBeFalse();

    lweAs($this, $this->mentor)->put(route('mentor.classes.notes.save', $session), ['summary' => 'Edited', 'key_points' => ['A'], 'action_items' => ['B'], 'recap' => 'R', 'publish' => true]);
    expect($session->fresh()->notes_published)->toBeTrue();
});
