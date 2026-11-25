<?php

use App\Models\AiLog;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';

/** Fake Claude answer: the "tool call" holds the structured data. */
function fakeClaude(string $tool, array $input): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => $tool, 'input' => $input]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 120, 'output_tokens' => 80],
        ]),
    ]);
}

beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
    Http::preventStrayRequests();
    config(['lwe.ai.api_key' => 'test-key']);
    Setting::put('ai_enabled', true);
    Setting::put('ai_daily_limit', 300);
    $this->admin = lweUser(User::ROLE_ADMIN);
});

it('drafts every wizard field from just a title', function () {
    fakeClaude('save_course_content', [
        'short_description' => 'Shopify store from zero',
        'category' => 'E-commerce',
        'level' => 'beginner',
        'description' => 'Is course mein aap seekhenge…',
        'what_you_learn' => ['Store setup', 'Product research'],
        'requirements' => ['Laptop'],
        'faqs' => [['q' => 'Kya beginners ke liye hai?', 'a' => 'Ji haan']],
        'skills' => ['Shopify Store Setup', 'Product Research'],
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.ai.course-draft'), ['title' => 'Shopify Mastery', 'notes' => 'beginners', 'language' => 'roman_urdu'])
        ->assertOk()
        ->assertJsonPath('category', 'E-commerce')
        ->assertJsonPath('skills.0', 'Shopify Store Setup')
        ->assertJsonPath('faqs.0.a', 'Ji haan');

    Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'test-key') && $r['tool_choice']['name'] === 'save_course_content');
    expect(AiLog::first())->feature->toBe('course_content')->input_tokens->toBe(120);
});

it('adds an AI outline as modules and lessons', function () {
    $course = lweCourse(10000);
    fakeClaude('save_outline', ['modules' => [
        ['title' => 'Week 1', 'lessons' => [
            ['title' => 'Intro', 'type' => 'live_class', 'summary' => 's', 'duration_minutes' => 60],
            ['title' => 'Task 1', 'type' => 'not-a-type', 'summary' => 's'],
        ]],
    ]]);

    $this->actingAs($this->admin)
        ->post(route('admin.ai.course-outline', $course), ['weeks' => 4, 'language' => 'english'])
        ->assertSessionHas('success');

    expect($course->modules()->count())->toBe(2) // 1 from the helper + 1 from AI
        ->and($course->lessons()->where('title', 'Task 1')->first()->type)->toBe('reading'); // unknown type made safe
});

it('flags a payment whose screenshot does not match', function () {
    Storage::fake('local');
    $student = lweUser();
    $course = lweCourse(10000);
    $enrollment = Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'pending_payment', 'fee_amount' => 10000, 'amount_due' => 10000]);
    $path = UploadedFile::fake()->image('r.jpg')->store('payment-screenshots', 'local');

    Payment::create(['enrollment_id' => $enrollment->id, 'user_id' => $student->id, 'amount' => 500, 'transaction_id' => 'TX999', 'status' => 'approved']);
    $payment = Payment::create(['enrollment_id' => $enrollment->id, 'user_id' => $student->id, 'amount' => 10000, 'transaction_id' => 'TX123', 'screenshot_path' => $path, 'status' => 'pending']);

    fakeClaude('save_payment_reading', [
        'is_payment_proof' => true, 'amount' => 1000, 'transaction_id' => 'TX 999', 'method' => 'jazzcash',
        'edited_signs' => false, 'notes' => 'Clear JazzCash receipt',
    ]);

    $this->actingAs($this->admin)->post(route('admin.ai.payment-check', $payment))->assertSessionHas('success');

    $check = $payment->fresh()->ai_check;
    expect($check['verdict'])->toBe('suspicious')
        ->and(collect($check['warnings'])->implode(' '))->toContain('Amount on screenshot')->toContain('already used')
        ->and($check['suggested_note'])->toContain('raqam');
});

it('explains when AI is switched off', function () {
    Setting::put('ai_enabled', false);

    $this->actingAs($this->admin)
        ->postJson(route('admin.ai.course-draft'), ['title' => 'X', 'language' => 'english'])
        ->assertStatus(422)->assertJsonPath('message', 'AI is switched off. Turn it on in Settings.');

    Http::assertNothingSent();
});

it('stops at the daily AI limit', function () {
    Setting::put('ai_daily_limit', 1);
    AiLog::create(['feature' => 'course_content', 'model' => 'x']);

    $this->actingAs($this->admin)
        ->postJson(route('admin.ai.course-draft'), ['title' => 'X', 'language' => 'english'])
        ->assertStatus(422);

    Http::assertNothingSent();
});

it('keeps AI tools away from students', function () {
    $this->actingAs(lweUser())->postJson(route('admin.ai.course-draft'), ['title' => 'X', 'language' => 'english'])->assertForbidden();
});

it('asks for a title before writing', function () {
    $this->actingAs($this->admin)->postJson(route('admin.ai.course-draft'), ['language' => 'english'])
        ->assertStatus(422)->assertJsonValidationErrors(['title' => 'Write the course title first.']);
    Http::assertNothingSent();
});
