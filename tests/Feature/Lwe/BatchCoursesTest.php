<?php

use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Channels\SafeMailChannel;
use App\Notifications\ClassesScheduled;
use App\Notifications\StudentEnrolled;
use App\Notifications\WelcomeStudent;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
    Notification::fake();
    $this->admin = lweUser(User::ROLE_ADMIN);
});

it('creates a batch with several courses and enrolls a student in all of them', function () {
    $a = lweCourse(null);
    $b = lweCourse(null);

    $this->actingAs($this->admin)->post(route('admin.batches.store'), [
        'course_ids' => [$a->id, $b->id], 'name' => 'Multan Batch 1', 'starts_on' => now()->toDateString(),
        'class_days' => ['mon'], 'class_time' => '20:00', 'class_duration_minutes' => 60, 'status' => 'running',
    ])->assertSessionHasNoErrors();

    $batch = Batch::where('name', 'Multan Batch 1')->firstOrFail();
    expect($batch->courses()->pluck('courses.id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());

    $this->actingAs($this->admin)->post(route('admin.enrollments.store'), [
        'name' => 'Hamza Ali', 'phone' => '03211112222', 'email' => 'hamza@example.com', 'batch_id' => $batch->id, 'source' => 'admin', 'activate_now' => true,
    ])->assertSessionHasNoErrors();

    $student = User::where('phone', '923211112222')->firstOrFail();
    expect(Enrollment::where('user_id', $student->id)->where('batch_id', $batch->id)->count())->toBe(2);
    Notification::assertSentTo($student, WelcomeStudent::class);
    // One seat per student, even with two courses
    expect($batch->seatsTaken())->toBe(1);
});

it('enrolls only in the ticked courses of a batch', function () {
    $a = lweCourse(null);
    $b = lweCourse(null);
    $batch = lweBatch($a);
    $batch->setCourses([$a->id, $b->id]);

    $this->actingAs($this->admin)->post(route('admin.enrollments.store'), [
        'name' => 'Sana', 'phone' => '03331234003', 'batch_id' => $batch->id, 'course_ids' => [$b->id], 'source' => 'admin', 'activate_now' => true,
    ])->assertSessionHasNoErrors();

    $student = User::where('phone', '923331234003')->firstOrFail();
    expect(Enrollment::where('user_id', $student->id)->pluck('course_id')->all())->toBe([$b->id]);
});

it('does not remove a course that has students from a batch', function () {
    $a = lweCourse(null);
    $b = lweCourse(null);
    $batch = lweBatch($a);
    $batch->setCourses([$a->id, $b->id]);
    Enrollment::create(['user_id' => lweUser()->id, 'course_id' => $b->id, 'batch_id' => $batch->id, 'status' => 'active']);

    $this->actingAs($this->admin)->put(route('admin.batches.update', $batch), [
        'course_ids' => [$a->id], 'name' => 'Batch 1', 'starts_on' => now()->toDateString(),
        'class_duration_minutes' => 60, 'status' => 'running',
    ])->assertSessionHasErrors('course_ids');

    expect($batch->courses()->count())->toBe(2);
});

it('schedules classes for one course of a multi-course batch and shows them only to its students', function () {
    lweZoomAccount();
    lweFakeZoom();
    $a = lweCourse(null);
    $b = lweCourse(null);
    $mentor = lweUser(User::ROLE_MENTOR);
    $batch = lweBatch($a, ['mentor_id' => $mentor->id]);
    $batch->setCourses([$a->id, $b->id]);
    $inB = lweUser();
    $inA = lweUser();
    Enrollment::create(['user_id' => $inB->id, 'course_id' => $b->id, 'batch_id' => $batch->id, 'status' => 'active']);
    Enrollment::create(['user_id' => $inA->id, 'course_id' => $a->id, 'batch_id' => $batch->id, 'status' => 'active']);

    $this->actingAs($this->admin)->post(route('admin.classes.store'), [
        'batch_id' => $batch->id, 'course_id' => $b->id, 'start_date' => lweNextDay('monday'), 'time' => '20:00', 'duration' => 60,
        'days' => ['mon'], 'weeks' => 1,
    ])->assertSessionHasNoErrors();

    $session = ClassSession::firstOrFail();
    expect($session->course_id)->toBe($b->id)
        ->and(ClassSession::forStudent($inB->id)->count())->toBe(1)
        ->and(ClassSession::forStudent($inA->id)->count())->toBe(0);

    Notification::assertSentTo($inB, ClassesScheduled::class);
    Notification::assertSentTo($mentor, ClassesScheduled::class);
    Notification::assertNotSentTo($inA, ClassesScheduled::class);
});

it('publishes a course with no lessons and adds a lesson from the Lessons page', function () {
    $course = Course::create(['title' => 'Canva Basics', 'status' => 'draft']);

    $this->actingAs($this->admin)->put(route('admin.courses.update', $course), ['step' => 'publish', 'status' => 'published'])
        ->assertSessionHasNoErrors();
    expect($course->fresh()->status)->toBe('published');

    $this->actingAs($this->admin)->get(route('admin.lessons.index'))->assertOk();

    $this->actingAs($this->admin)->post(route('admin.lessons.create'), [
        'course_id' => $course->id, 'title' => 'Canva account banana', 'type' => 'reading', 'unlock_type' => 'immediate',
    ])->assertSessionHasNoErrors();

    $lesson = $course->lessons()->firstOrFail();
    expect($lesson->title)->toBe('Canva account banana')
        ->and($lesson->module->title)->toBe('Lessons');

    $this->actingAs($this->admin)->post(route('admin.lessons.create'), [
        'course_id' => $course->id, 'new_module' => 'Week 2', 'title' => 'Brand kit', 'type' => 'reading', 'unlock_type' => 'immediate',
    ])->assertSessionHasNoErrors();
    expect($course->modules()->pluck('title')->all())->toContain('Week 2');
});

it('emails students and staff only when email is switched on', function () {
    $student = lweUser(User::ROLE_STUDENT, ['email' => 'ali@example.com']);
    $admin = lweUser(User::ROLE_ADMIN, ['email' => 'boss@example.com']);

    expect((new WelcomeStudent)->via($student))->toContain(SafeMailChannel::class);

    Setting::put('email_students', false);
    expect((new WelcomeStudent)->via($student))->not->toContain(SafeMailChannel::class);

    $enrollment = Enrollment::create(['user_id' => $student->id, 'course_id' => lweCourse(null)->id, 'status' => 'active']);
    expect((new StudentEnrolled($enrollment))->via($admin))->toContain(SafeMailChannel::class);

    Setting::put('email_enabled', false);
    expect((new StudentEnrolled($enrollment))->via($admin))->not->toContain(SafeMailChannel::class);
});

it('tells admins when a student enrolls on their own', function () {
    $course = lweCourse(null);
    $student = lweUser();

    $this->actingAs($student)->post(route('student.courses.enroll', $course->slug))->assertSessionHasNoErrors();

    Notification::assertSentTo($this->admin, StudentEnrolled::class);
});

it('adds a new course to a running batch and gives it to the students already there', function () {
    $a = lweCourse(5000);
    $b = lweCourse(8000);
    $batch = lweBatch($a);
    $s1 = lweUser();
    $s2 = lweUser();
    foreach ([$s1, $s2] as $s) {
        Enrollment::create(['user_id' => $s->id, 'course_id' => $a->id, 'batch_id' => $batch->id, 'status' => 'active']);
    }

    $this->actingAs($this->admin)->put(route('admin.batches.update', $batch), [
        'course_ids' => [$a->id, $b->id], 'name' => 'Batch 1', 'starts_on' => now()->toDateString(),
        'class_duration_minutes' => 60, 'status' => 'running', 'enroll_existing' => true,
    ])->assertSessionHasNoErrors();

    $inB = Enrollment::where('course_id', $b->id)->where('batch_id', $batch->id)->get();
    expect($inB)->toHaveCount(2)
        ->and($inB->pluck('status')->unique()->all())->toBe(['active'])
        ->and((float) $inB->first()->amount_due)->toBe(0.0)
        ->and($batch->seatsTaken())->toBe(2);
});

it('adds a course to batches from the course wizard', function () {
    $a = lweCourse(null);
    $batch = lweBatch($a);
    $student = lweUser();
    Enrollment::create(['user_id' => $student->id, 'course_id' => $a->id, 'batch_id' => $batch->id, 'status' => 'active']);
    $new = Course::create(['title' => 'Canva Basics', 'status' => 'draft']);

    $this->actingAs($this->admin)->put(route('admin.courses.update', $new), [
        'step' => 'publish', 'status' => 'published', 'batch_ids' => [$batch->id], 'enroll_existing' => true,
    ])->assertSessionHasNoErrors();

    expect($batch->hasCourse($new->id))->toBeTrue()
        ->and(Enrollment::where('user_id', $student->id)->where('course_id', $new->id)->value('status'))->toBe('active');

    // Unticking the batch again leaves the student's enrollment, so the course stays in the batch.
    $this->actingAs($this->admin)->put(route('admin.courses.update', $new), ['step' => 'publish', 'status' => 'published', 'batch_ids' => []]);
    expect($batch->hasCourse($new->id))->toBeTrue();
});

it('lets a mentor add lessons only to their own courses', function () {
    $mine = Course::create(['title' => 'Shopify', 'status' => 'published']);
    $notMine = Course::create(['title' => 'Amazon', 'status' => 'published']);
    $mentor = lweUser(User::ROLE_MENTOR);
    $mine->mentors()->attach($mentor);

    $this->actingAs($mentor)->get(route('manage.lessons.index'))
        ->assertInertia(fn ($p) => $p->where('area', 'manage')->has('courses', 1)->where('courses.0.id', $mine->id));

    $this->actingAs($mentor)->post(route('manage.lessons.create'), [
        'course_id' => $mine->id, 'title' => 'Store setup', 'type' => 'recorded_video',
        'video_url' => 'https://youtu.be/abc123', 'unlock_type' => 'immediate',
    ])->assertSessionHasNoErrors();
    expect($mine->lessons()->count())->toBe(1);

    $this->actingAs($mentor)->post(route('manage.lessons.create'), [
        'course_id' => $notMine->id, 'title' => 'Not allowed', 'type' => 'reading', 'unlock_type' => 'immediate',
    ])->assertForbidden();
    $this->actingAs($mentor)->get(route('manage.courses.curriculum', $notMine))->assertForbidden();
    $this->actingAs($mentor)->get(route('manage.courses.curriculum', $mine))->assertOk();

    // Admin pages stay closed to mentors.
    $this->actingAs($mentor)->get(route('admin.lessons.index'))->assertForbidden();
});

it('lists every course mentor of a batch', function () {
    $a = lweCourse(null);
    $b = lweCourse(null);
    $m1 = lweUser(User::ROLE_MENTOR, ['name' => 'Younis']);
    $m2 = lweUser(User::ROLE_MENTOR, ['name' => 'Haider']);
    $a->mentors()->attach($m1);
    $b->mentors()->attach($m2);
    $batch = lweBatch($a);
    $batch->setCourses([$a->id, $b->id]);

    $this->actingAs($this->admin)->get(route('admin.batches.index'))->assertInertia(fn ($p) => $p
        ->has('batches.data.0.mentors', 2)
        ->has('courses', 2)
        ->has('courses.0.mentors', 1));
});
