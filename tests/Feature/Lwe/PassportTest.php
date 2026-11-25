<?php

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\MentorProfile;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SessionParticipant;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Notifications\CertificateIssued;
use App\Notifications\StudentAtRisk;
use App\Notifications\TaskDueSoon;
use App\Services\ProgressService;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
    Notification::fake();

    $this->course = lweCourse(null, ['min_attendance_percent' => 75, 'min_quiz_percent' => 50, 'weight_attendance' => 30, 'weight_tasks' => 50, 'weight_quiz' => 20, 'require_compulsory_tasks' => true]);
    $this->course->skills()->create(['name' => 'Product Research', 'slug' => 'product-research']);
    $this->mentor = lweUser(User::ROLE_MENTOR);
    $this->course->mentors()->attach($this->mentor);
    $this->batch = lweBatch($this->course, ['mentor_id' => $this->mentor->id, 'ends_on' => now()->subDay()->toDateString()]);
    $this->student = lweUser();
    $this->enrollment = Enrollment::create(['user_id' => $this->student->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'status' => 'active', 'activated_at' => now()]);
});

/** 4 ended classes, a graded task and a passed quiz for $this->student. */
function lweGoodRecord($t, int $present = 4): void
{
    foreach (range(1, 4) as $i) {
        $s = ClassSession::create(['course_id' => $t->course->id, 'batch_id' => $t->batch->id, 'title' => "C{$i}", 'starts_at' => now()->subDays($i), 'duration_minutes' => 60, 'status' => 'ended']);
        Attendance::create(['class_session_id' => $s->id, 'user_id' => $t->student->id, 'status' => $i <= $present ? 'present' : 'absent', 'minutes' => 60]);
    }
    $task = Task::create(['course_id' => $t->course->id, 'title' => 'T', 'instructions' => 'I', 'status' => 'published', 'max_marks' => 10, 'due_at' => now()->subDay()]);
    TaskSubmission::create(['task_id' => $task->id, 'user_id' => $t->student->id, 'body' => 'x', 'submitted_at' => now()->subDays(2), 'status' => 'graded', 'marks' => 8]);
    $quiz = Quiz::create(['course_id' => $t->course->id, 'title' => 'Q', 'status' => 'published']);
    QuizAttempt::create(['quiz_id' => $quiz->id, 'user_id' => $t->student->id, 'question_order' => [], 'started_at' => now(), 'submitted_at' => now(), 'percent' => 90, 'score' => 9, 'max_score' => 10, 'passed' => true]);
}

it('calculates the weighted score from attendance, tasks and quizzes', function () {
    lweGoodRecord($this, present: 3); // 75% attendance

    $r = app(ProgressService::class)->calculate($this->enrollment->load('course'));

    expect($r['attendance'])->toBe(75.0)
        ->and($r['tasks'])->toBe(80.0)
        ->and($r['quiz'])->toBe(90.0)
        ->and($r['score'])->toBe(round((75 * 30 + 80 * 50 + 90 * 20) / 100, 1))
        ->and($r['eligible'])->toBeTrue();
});

it('is not eligible with low attendance and says why', function () {
    lweGoodRecord($this, present: 1); // 25%

    $r = app(ProgressService::class)->calculate($this->enrollment->load('course'));

    expect($r['eligible'])->toBeFalse()->and($r['notes'][0])->toContain('Attendance 25');
});

it('issues certificates automatically when the batch has ended, and nudges students at risk', function () {
    lweGoodRecord($this);
    $weak = lweUser();
    $weakEnrollment = Enrollment::create(['user_id' => $weak->id, 'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'status' => 'active', 'activated_at' => now()->subDays(20), 'created_at' => now()->subDays(20)]);

    $this->artisan('lwe:automation')->assertSuccessful();

    $cert = Certificate::firstOrFail();
    expect($cert->user_id)->toBe($this->student->id)
        ->and($cert->code)->toBe(sprintf('LWE-CERT-%06d', $cert->id))
        ->and($this->enrollment->fresh()->status)->toBe('completed');
    Notification::assertSentTo($this->student, CertificateIssued::class);

    expect($weakEnrollment->fresh()->progress_status)->toBe('at_risk');
    Notification::assertSentTo($weak, StudentAtRisk::class);

    // Only once a week
    $this->artisan('lwe:automation')->assertSuccessful();
    Notification::assertSentToTimes($weak, StudentAtRisk::class, 1);
});

it('shows a public certificate check and a public Skill Passport', function () {
    lweGoodRecord($this);
    $cert = app(\App\Services\CertificateService::class)->issue($this->enrollment);

    $this->get(route('certificates.show', $cert->code))->assertOk()
        ->assertInertia(fn ($p) => $p->component('Public/Certificate')->where('found', true)->where('certificate.valid', true)->where('certificate.skills.0', 'Product Research'));
    $this->get(route('certificates.show', 'LWE-CERT-999999'))->assertOk()->assertInertia(fn ($p) => $p->where('found', false));

    $code = $this->student->fresh()->passport_code;
    $this->get(route('passport.show', $code))->assertOk()
        ->assertInertia(fn ($p) => $p->component('Public/Passport')->where('verified_skills.0', 'Product Research')->where('student.phone', null));

    // Private passport is hidden from the public
    $this->student->update(['passport_public' => false]);
    $this->get(route('passport.show', $code))->assertNotFound();

    // Revoked certificate shows as not valid
    app(\App\Services\CertificateService::class)->revoke($cert, 'Copied tasks', lweUser(User::ROLE_ADMIN));
    $this->get(route('certificates.show', $cert->code))->assertInertia(fn ($p) => $p->where('certificate.valid', false));
});

it('lets staff issue a certificate only when eligible, unless an admin overrides', function () {
    $admin = lweUser(User::ROLE_ADMIN);
    lweAs($this, $this->mentor)->post(route('manage.progress.issue', $this->enrollment))->assertSessionHas('error');
    expect(Certificate::count())->toBe(0);

    lweAs($this, $admin)->post(route('manage.progress.issue', $this->enrollment), ['override' => true])->assertSessionHas('success');
    expect(Certificate::count())->toBe(1);

    lweAs($this, $this->mentor)->get(route('manage.progress.index'))->assertOk();
});

it('sends task-due reminders once to students who have not submitted', function () {
    Task::create(['course_id' => $this->course->id, 'title' => 'Due soon', 'instructions' => 'I', 'status' => 'published', 'due_at' => now()->addHours(5)]);

    $this->artisan('lwe:automation --tasks')->assertSuccessful();
    $this->artisan('lwe:automation --tasks')->assertSuccessful();

    Notification::assertSentToTimes($this->student, TaskDueSoon::class, 1);
});

it('runs a recorded lecture by the clock and counts attendance from watching', function () {
    $session = ClassSession::create([
        'course_id' => $this->course->id, 'batch_id' => $this->batch->id, 'title' => 'Video class', 'kind' => 'recorded',
        'video_url' => 'https://youtu.be/abcdefghijk', 'starts_at' => now()->subMinutes(10), 'duration_minutes' => 20, 'status' => 'scheduled',
    ]);

    lweAs($this, $this->student)->get(route('student.classes.join', $session))
        ->assertInertia(fn ($p) => $p->component('Classes/Replay')->where('session.video_url', 'https://youtu.be/abcdefghijk'));
    expect(SessionParticipant::where('class_session_id', $session->id)->count())->toBe(1);

    // Watched from 10 minutes in until the end
    SessionParticipant::where('class_session_id', $session->id)->update(['joined_at' => now()->subMinutes(10), 'left_at' => now()->addMinutes(10)]);
    $this->travel(11)->minutes();
    $this->artisan('lwe:class-reminders')->assertSuccessful();

    expect($session->fresh()->status)->toBe('ended');
    expect(Attendance::where('class_session_id', $session->id)->where('user_id', $this->student->id)->value('status'))->toBe('present');
});

it('lets an admin add a mentor and publish their landing page', function () {
    $admin = lweUser(User::ROLE_ADMIN);
    lweAs($this, $admin)->post(route('admin.mentors.store'), ['name' => 'Haider Qazi', 'phone' => '03111234567', 'password' => 'secret123'])->assertRedirect();
    $mentor = User::where('phone', '923111234567')->firstOrFail();
    expect($mentor->hasRole(User::ROLE_MENTOR))->toBeTrue();

    $profile = MentorProfile::where('user_id', $mentor->id)->firstOrFail();
    auth()->logout();
    $this->get(route('mentors.show', $profile->slug))->assertNotFound(); // hidden from the public until published

    lweAs($this, $mentor)->post(route('manage.mentors.update', $mentor), [
        'slug' => 'haider-qazi', 'headline' => 'E-commerce head', 'bio' => 'Bio', 'is_published' => true,
        'expertise' => ['Amazon'], 'success_stories' => [['name' => 'Ali', 'story' => 'Got first sale', 'result' => '$1,000']],
    ])->assertSessionHasNoErrors();

    auth()->logout();
    $this->get(route('mentors.show', 'haider-qazi'))->assertOk()
        ->assertSee('Haider Qazi')->assertSee('"@type":"Person"', false);

    // Another mentor cannot edit this page
    lweAs($this, lweUser(User::ROLE_MENTOR))->get(route('manage.mentors.edit', $mentor))->assertForbidden();
});

it('shows packages, review videos and FAQs on a mentor landing page, with SEO and the pixel', function () {
    Setting::put('meta_pixel_id', '1234567890123');
    $mentor = lweUser(User::ROLE_MENTOR, ['name' => 'Haider Qazi']);
    lweAs($this, $mentor)->post(route('manage.mentors.update', $mentor), [
        'slug' => 'haider-qazi', 'headline' => 'E-commerce head', 'is_published' => true, 'whatsapp' => '03001234567',
        'packages' => [
            ['name' => 'Starter', 'old_price' => 10000, 'price' => 7000, 'features' => ['8 live classes', ''], 'featured' => false],
            ['name' => 'Professional', 'price' => 18000, 'features' => ['16 live classes'], 'featured' => true],
        ],
        'review_videos' => [['url' => 'https://youtu.be/dQw4w9WgXcQ', 'name' => 'Ali']],
        'faqs' => [['q' => 'Kya beginners join kar sakte hain?', 'a' => 'Ji haan.']],
        'seo_title' => 'Haider Qazi — Amazon mentor in Pakistan',
    ])->assertSessionHasNoErrors();

    $p = MentorProfile::where('slug', 'haider-qazi')->firstOrFail();
    expect($p->packages[0]['features'])->toBe(['8 live classes'])->and($p->packages[1]['old_price'])->toBeNull();

    auth()->logout();
    $this->get(route('mentors.show', 'haider-qazi'))->assertOk()
        ->assertSee('<title>Haider Qazi — Amazon mentor in Pakistan</title>', false)
        ->assertSee('Save 30%')->assertSee('7,000')
        ->assertSee('wa.me/923001234567', false)
        ->assertSee('dQw4w9WgXcQ', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee("fbq('init', \"1234567890123\")", false);

    $this->get(route('sitemap'))->assertOk()->assertSee(route('mentors.show', 'haider-qazi'), false);
    $this->get(route('robots'))->assertOk()->assertSee('Sitemap:');
    $this->get(route('mentors.index'))->assertOk()->assertSee('Haider Qazi')->assertSee('From PKR 7,000');

    // Only YouTube links are accepted for review videos.
    lweAs($this, $mentor)->post(route('manage.mentors.update', $mentor), [
        'slug' => 'haider-qazi', 'review_videos' => [['url' => 'https://vimeo.com/1']],
    ])->assertSessionHasErrors('review_videos.0.url');
});

it('shows the journey line, outcomes, countdown and seats on a mentor page', function () {
    $mentor = lweUser(User::ROLE_MENTOR, ['name' => 'Haider Qazi']);
    lweAs($this, $mentor)->post(route('manage.mentors.update', $mentor), [
        'slug' => 'haider-qazi', 'is_published' => true,
        'journey' => [['title' => 'Product Research', 'points' => ['Find demand', '', 'Pick a product']], ['title' => 'Ads Creation', 'points' => []]],
        'outcomes' => [['title' => 'Run your first ads', 'text' => 'Small budgets that work']],
        'platforms' => ['Amazon', ' Daraz ', ''],
        'timeline' => [['year' => '2018', 'text' => 'First product on Daraz']],
        'offer_ends_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'seats_total' => 40, 'seats_left' => 12,
        'packages' => [['name' => 'Starter', 'price' => 7000, 'features' => ['8 live classes']], ['name' => 'Pro', 'price' => 18000, 'features' => ['16 live classes']]],
    ])->assertSessionHasNoErrors();

    $p = MentorProfile::where('slug', 'haider-qazi')->firstOrFail();
    expect($p->journey[0]['points'])->toBe(['Find demand', 'Pick a product'])
        ->and($p->platforms)->toBe(['Amazon', 'Daraz'])
        ->and($p->seats_left)->toBe(12);

    auth()->logout();
    $this->get(route('mentors.show', 'haider-qazi'))->assertOk()
        ->assertSee('Product Research')->assertSee('Complete course roadmap')->assertSee('First product on Daraz')
        ->assertSee('12 of 40')->assertSee('data-ends=', false)->assertSee('Compare packages');

    // Seats left cannot be more than the total.
    lweAs($this, $mentor)->post(route('manage.mentors.update', $mentor), ['slug' => 'haider-qazi', 'seats_total' => 10, 'seats_left' => 20])
        ->assertSessionHasErrors('seats_left');
});

it('accepts package prices typed with commas or Rs', function () {
    $mentor = lweUser(User::ROLE_MENTOR, ['name' => 'Muhammad Imran']);
    lweAs($this, $mentor)->post(route('manage.mentors.update', $mentor), [
        'slug' => 'muhammad-imran',
        'packages' => [['name' => 'Starter', 'old_price' => '10,000', 'price' => 'Rs 7,000'], ['name' => 'Free intro', 'price' => '0']],
        'seats_total' => '40', 'seats_left' => '12',
    ])->assertSessionHasNoErrors();

    $p = MentorProfile::where('slug', 'muhammad-imran')->firstOrFail();
    expect($p->packages[0]['price'])->toBe(7000)->and($p->packages[0]['old_price'])->toBe(10000)->and($p->packages[1]['price'])->toBe(0);

    lweAs($this, $mentor)->post(route('manage.mentors.update', $mentor), [
        'slug' => 'muhammad-imran', 'packages' => [['name' => 'Starter', 'price' => '']],
    ])->assertSessionHasErrors(['packages.0.price' => 'Package 1 needs a price — numbers only, e.g. 7000.']);
});
