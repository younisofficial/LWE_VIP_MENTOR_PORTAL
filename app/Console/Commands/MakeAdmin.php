<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Phone;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/** Creates the first Super Admin (fresh install / live server). Asks for the password without showing it. */
class MakeAdmin extends Command
{
    protected $signature = 'lwe:make-admin';

    protected $description = 'Create a Super Admin account';

    public function handle(): int
    {
        if (! Role::where('name', User::ROLE_SUPER_ADMIN)->exists()) {
            $this->call('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);
        }

        $name = $this->ask('Full name');
        $phone = Phone::normalize((string) $this->ask('Mobile number (used to log in), e.g. 03001234567'));
        if (! $phone) {
            $this->error('That is not a valid Pakistani mobile number.');

            return self::FAILURE;
        }
        $email = $this->ask('Email (optional, press Enter to skip)') ?: null;
        $password = (string) $this->secret('Password (at least 8 letters — it will not show while typing)');
        if (mb_strlen($password) < 8 || $password !== $this->secret('Type the password again')) {
            $this->error('Passwords are too short or do not match.');

            return self::FAILURE;
        }
        if (User::where('phone', $phone)->orWhere(fn ($q) => $email ? $q->where('email', $email) : $q->whereRaw('1 = 0'))->exists()) {
            $this->error('An account with this phone or email already exists.');

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'phone' => $phone, 'email' => $email, 'password' => Hash::make($password), 'status' => 'active']);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        $this->info("Super Admin created: {$user->name} ({$phone}). Log in with this phone number and the password.");

        return self::SUCCESS;
    }
}
