<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeviceGuard;
use App\Services\Phone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/** Student self-registration (phone is required, email optional). */
class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, DeviceGuard $devices): RedirectResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^923\d{9}$/', Rule::unique(User::class)],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'city' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'phone.regex' => 'Enter a valid Pakistani mobile number, e.g. 0300 1234567.',
            'phone.unique' => 'This number is already registered. Try logging in or use "Forgot password".',
        ]);

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'city' => $request->city,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole(User::ROLE_STUDENT);
        $user->notify(new \App\Notifications\WelcomeStudent);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $devices->startSession($user, $request);

        return redirect(route('dashboard', absolute: false));
    }
}
