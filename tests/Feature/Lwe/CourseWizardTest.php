<?php

use App\Models\Course;
use App\Models\User;

require_once __DIR__.'/helpers.php';


beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
});

it('walks through the course wizard', function () {
    $admin = lweUser(User::ROLE_ADMIN);
    $mentor = lweUser(User::ROLE_MENTOR);

    $this->actingAs($admin)->post(route('admin.courses.store'), [
        'title' => 'Amazon FBA', 'level' => 'beginner', 'mentor_ids' => [$mentor->id], 'is_vip' => true,
    ])->assertSessionHasNoErrors();

    $course = Course::first();
    expect($course->status)->toBe('draft')->and($course->mentors)->toHaveCount(1);

    $this->actingAs($admin)->put(route('admin.courses.update', $course), [
        'step' => 'fee', 'is_free' => true, 'coupons_allowed' => false,
    ])->assertRedirect(route('admin.courses.edit', [$course, 'step' => 'completion']));
    expect($course->fresh()->is_free)->toBeTrue();

    $this->actingAs($admin)->put(route('admin.courses.update', $course), [
        'step' => 'completion', 'min_attendance_percent' => 75, 'min_quiz_percent' => 50,
        'weight_attendance' => 30, 'weight_tasks' => 30, 'weight_quiz' => 30, // 90 ≠ 100
    ])->assertSessionHasErrors('weight_attendance');

    $this->actingAs($admin)->put(route('admin.courses.update', $course), ['step' => 'skills', 'skill_names' => ['Amazon PPC', 'Product Hunting']]);
    expect($course->skills()->count())->toBe(2);

    // A course can be published before it has lessons (lessons are added later from the Lessons page)
    $this->actingAs($admin)->put(route('admin.courses.update', $course), ['step' => 'publish', 'status' => 'published'])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.courses.index'));
    expect($course->fresh()->status)->toBe('published');
});

it('saves AI-length details and moves to the next step', function () {
    $admin = lweUser(User::ROLE_ADMIN);
    $course = Course::create(['title' => 'Amazon FBA Mastery', 'status' => 'draft']);
    $long = str_repeat('Product research aur supplier hunting seekhein. ', 6); // ~290 characters

    $this->actingAs($admin)->put(route('admin.courses.update', $course), [
        'step' => 'details',
        'description' => 'Ye course Amazon FBA ka basic process sikhata hai.',
        'what_you_learn' => [$long, 'Listing banana'],
        'requirements' => ['Laptop'],
        'faqs' => [['q' => 'Kya beginners ke liye hai?', 'a' => 'Ji haan']],
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.courses.edit', [$course, 'step' => 'skills']));

    $this->actingAs($admin)->put(route('admin.courses.update', $course), [
        'step' => 'details',
        'what_you_learn' => [str_repeat('x', 501)],
    ])->assertSessionHasErrors('what_you_learn.0');
});

test('a step sent without its own fields does not wipe saved data', function () {
    $admin = lweUser(User::ROLE_ADMIN);
    $course = Course::create(['title' => 'Guard Course', 'status' => 'draft', 'description' => 'Keep me']);

    $this->actingAs($admin)->put(route('admin.courses.update', $course), ['step' => 'details', 'skill_names' => ['X']])
        ->assertRedirect();

    expect($course->fresh()->description)->toBe('Keep me');
});
