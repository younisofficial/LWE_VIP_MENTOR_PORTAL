<?php

use App\Models\Setting;
use App\Models\User;

require_once __DIR__.'/helpers.php';


beforeEach(function () {
    lweSeedRoles();
    $this->withoutVite();
});

it('lets a student log in with a phone number in any format', function () {
    $user = lweUser(User::ROLE_STUDENT, ['phone' => '923001234567']);

    $this->post('/login', ['login' => '0300 1234567', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->current_session_id)->not->toBeNull()
        ->and($user->loginLogs()->count())->toBe(1);
});

it('logs out the old device when the same account logs in elsewhere', function () {
    $user = lweUser();

    $this->actingAs($user)->get('/today')->assertOk();         // device A adopts its session

    // Device B logs in → current_session_id changes
    $user->forceFill(['current_session_id' => 'another-device-session'])->save();

    $this->get('/today')->assertRedirect(route('login'));       // device A is kicked out
    $this->assertGuest();
});

it('blocks login from a different device when the account is device-locked', function () {
    $user = lweUser();
    $user->forceFill(['locked_device_hash' => hash('sha256', 'Some Other Browser')])->save();

    $this->withHeader('User-Agent', 'My Browser')
        ->post('/login', ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
    expect($user->loginLogs()->first()->result)->toBe('blocked_device');
});

it('does not let suspended users log in', function () {
    $user = lweUser(User::ROLE_STUDENT, ['status' => 'suspended']);

    $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('asks admins for a second-step code', function () {
    Setting::put('admin_two_factor', true);
    $admin = lweUser(User::ROLE_ADMIN);

    $this->post('/login', ['login' => $admin->email, 'password' => 'password'])->assertRedirect(route('two-factor.show'));
    $this->get('/admin')->assertRedirect(route('two-factor.show'));
    $this->assertAuthenticatedAs($admin);
});

it('resets a password with a one-time code', function () {
    $user = lweUser(User::ROLE_STUDENT, ['phone' => '923009999999']);

    // Capture the code by replacing the WhatsApp sender with a fake.
    $fake = new class extends \App\Services\WaFlowClient
    {
        public ?string $sent = null;

        public function send(string $phone, string $message): bool
        {
            preg_match('/\d{6}/', $message, $m);
            $this->sent = $m[0] ?? null;

            return true;
        }

        public function enabled(): bool
        {
            return true;
        }
    };
    app()->instance(\App\Services\WaFlowClient::class, $fake);

    $this->post('/forgot-password-code', ['login' => '03009999999'])->assertRedirect(route('password.otp.reset'));
    $sent = $fake->sent;
    expect($sent)->toHaveLength(6);

    $this->post('/reset-password-code', ['code' => '000000', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertSessionHasErrors('code');

    $this->post('/reset-password-code', ['code' => $sent, 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertRedirect(route('login'));

    $this->post('/login', ['login' => '03009999999', 'password' => 'new-password-1']);
    $this->assertAuthenticatedAs($user);
});

it('registers a student with a phone number', function () {
    $this->post('/register', [
        'name' => 'New Student', 'phone' => '0311 2223334', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('phone', '923112223334')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('student'))->toBeTrue()
        ->and($user->student_code)->toBe(sprintf('LWE-STU-%06d', $user->id));
});
