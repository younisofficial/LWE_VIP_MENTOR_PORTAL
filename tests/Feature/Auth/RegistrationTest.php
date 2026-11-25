<?php

// Replaces Breeze's test: students register with a phone number (email optional).

test('registration screen can be rendered', function () {
    $this->get('/register')->assertStatus(200);
});

test('new users can register', function () {
    (new \Database\Seeders\RolesSeeder)->run();

    $this->post('/register', [
        'name' => 'Test User',
        'phone' => '03001234567',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
