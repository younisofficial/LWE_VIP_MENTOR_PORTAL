<?php

use App\Models\Coupon;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';


beforeEach(function () {
    lweSeedRoles();
    Notification::fake();
    $this->withoutVite();
    config(['lwe.ai.api_key' => null]); // never call the real AI from tests
});

it('activates a free course immediately', function () {
    $student = lweUser();
    $course = lweCourse(null);
    $batch = lweBatch($course);

    $this->actingAs($student)
        ->post(route('student.courses.enroll', $course->slug), ['batch_id' => $batch->id])
        ->assertRedirect(route('student.my-courses.show', $course->slug));

    expect(Enrollment::first()->status)->toBe('active');
});

it('keeps a paid course locked until an admin approves the payment', function () {
    Storage::fake('local');
    $student = lweUser();
    $admin = lweUser(User::ROLE_ADMIN);
    $course = lweCourse(10000);
    $batch = lweBatch($course);

    $this->actingAs($student)->post(route('student.courses.enroll', $course->slug), ['batch_id' => $batch->id]);
    $enrollment = Enrollment::first();
    expect($enrollment->status)->toBe('pending_payment');

    $this->actingAs($student)->get(route('student.my-courses.show', $course->slug))
        ->assertRedirect(route('student.payments.create', $enrollment));

    $this->actingAs($student)->post(route('student.payments.store', $enrollment), [
        'amount' => 10000, 'method' => 'jazzcash', 'transaction_id' => 'TX123',
        'screenshot' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertSessionHasNoErrors();

    $payment = $enrollment->payments()->first();
    Storage::disk('local')->assertExists($payment->screenshot_path);

    $this->actingAs($admin)->withSession(['2fa_pending' => false])
        ->post(route('admin.payments.approve', $payment))->assertSessionHasNoErrors();

    expect($enrollment->fresh()->status)->toBe('active')
        ->and((float) $enrollment->fresh()->amount_due)->toBe(0.0);
});

it('applies a coupon and counts its use', function () {
    $student = lweUser();
    $course = lweCourse(10000);
    $batch = lweBatch($course);
    $coupon = Coupon::create(['code' => 'half', 'type' => 'percent', 'value' => 50, 'max_uses' => 1, 'is_active' => true]);

    $this->actingAs($student)->post(route('student.courses.enroll', $course->slug), ['batch_id' => $batch->id, 'coupon' => 'HALF']);

    $enrollment = Enrollment::first();
    expect((float) $enrollment->amount_due)->toBe(5000.0)
        ->and($coupon->fresh()->used_count)->toBe(1);

    // Max uses reached
    $other = lweUser();
    $this->actingAs($other)->post(route('student.courses.enroll', $course->slug), ['batch_id' => $batch->id, 'coupon' => 'HALF'])
        ->assertSessionHasErrors('coupon');
});

it('does not overfill a batch', function () {
    $course = lweCourse(null);
    $batch = lweBatch($course, ['seats' => 1]);

    $this->actingAs(lweUser())->post(route('student.courses.enroll', $course->slug), ['batch_id' => $batch->id]);
    $this->actingAs(lweUser())->post(route('student.courses.enroll', $course->slug), ['batch_id' => $batch->id])
        ->assertSessionHasErrors('batch_id');
});

it('imports students from CSV without duplicates', function () {
    $admin = lweUser(User::ROLE_ADMIN);
    $course = lweCourse(5000);
    $batch = lweBatch($course);
    lweUser(User::ROLE_STUDENT, ['phone' => '923001111111']); // already exists

    $csv = "name,phone,email,city\nAli,03001111111,,Multan\nSara,0300-2222222,sara@x.com,Lahore\nBad Row,123,,\n";
    $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

    $this->actingAs($admin)->post(route('admin.enrollments.import'), [
        'file' => $file, 'batch_id' => $batch->id, 'source' => 'scholarship', 'activate_now' => true,
    ])->assertSessionHas('importReport', fn ($r) => $r['created'] === 1 && $r['existing'] === 1 && $r['enrolled'] === 2 && $r['skipped'] === 1);

    expect(User::where('phone', '923002222222')->first()->hasRole('student'))->toBeTrue()
        ->and(Enrollment::where('status', 'active')->count())->toBe(2);
});

it('respects drip rules', function () {
    $student = lweUser();
    $course = lweCourse(null);
    $this->actingAs($student)->post(route('student.courses.enroll', $course->slug));

    $lessonB = $course->lessons()->where('title', 'Lesson B')->first();
    $lessonA = $course->lessons()->where('title', 'Lesson A')->first();

    $this->actingAs($student)->get(route('student.lessons.show', [$course->slug, $lessonB]))
        ->assertRedirect(route('student.my-courses.show', $course->slug));

    $this->actingAs($student)->post(route('student.lessons.complete', [$course->slug, $lessonA]));
    $this->actingAs($student)->get(route('student.lessons.show', [$course->slug, $lessonB]))->assertOk();
});

it('keeps students out of the admin panel', function () {
    $this->actingAs(lweUser())->get('/admin')->assertForbidden();
});
