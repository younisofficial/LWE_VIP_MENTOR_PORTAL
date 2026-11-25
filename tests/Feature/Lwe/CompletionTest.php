<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
});

it('imports old passport IDs and gives new students the next free number', function () {
    $admin = lweUser(User::ROLE_SUPER_ADMIN);
    $existing = lweUser(User::ROLE_STUDENT, ['phone' => '923001234567']);
    $csv = "passport_code,name,phone,email\nLWE-SP-000120,Ali Khan,0300 1234567,\nLWE-SP-000121,Sana Iqbal,03331234003,sana@example.com\nBAD-1,X,03331234004,\n";

    lweAs($this, $admin)->post(route('admin.passports.import'), ['file' => UploadedFile::fake()->createWithContent('old.csv', $csv)])
        ->assertSessionHas('success');

    expect($existing->fresh()->passport_code)->toBe('LWE-SP-000120')
        ->and(User::where('phone', '923331234003')->value('passport_code'))->toBe('LWE-SP-000121');

    // A new student never gets an old number
    expect(lweUser()->passportCode())->toBe('LWE-SP-000122');
});

it('sends WhatsApp in the shape set in .env', function () {
    config(['lwe.waflow' => [
        'enabled' => true, 'base_url' => 'https://hub.test', 'token' => 'secret', 'send_path' => '/api/send',
        'auth' => 'header:X-API-KEY', 'phone_field' => 'to', 'message_field' => 'text', 'phone_format' => '+92',
        'extra' => '{"type":"text"}', 'as_form' => false,
    ]]);
    Http::fake(['hub.test/*' => Http::response(['ok' => true])]);

    expect(app(\App\Services\WaFlowClient::class)->send('0300 1234567', 'Hello'))->toBeTrue();

    Http::assertSent(fn ($r) => $r->url() === 'https://hub.test/api/send'
        && $r->hasHeader('X-API-KEY', 'secret')
        && $r['to'] === '+923001234567' && $r['text'] === 'Hello' && $r['type'] === 'text');
});
