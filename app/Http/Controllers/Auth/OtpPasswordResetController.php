<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot password": phone/email → 6-digit code on WhatsApp (or email) → new password.
 * Also used for the first login of students imported by CSV.
 */
class OtpPasswordResetController extends Controller
{
    public function request(): Response
    {
        return Inertia::render('Auth/ForgotPasswordOtp', ['status' => session('status')]);
    }

    public function send(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate(['login' => ['required', 'string', 'max:255']]);

        $key = 'otp-send:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Too many requests. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        RateLimiter::hit($key, 600);

        $user = $this->findUser((string) $request->input('login'));

        // Same message whether or not the account exists (no account guessing).
        if ($user) {
            $otp->issue($user, 'password_reset');
            $request->session()->put('otp_reset_user', $user->id);
        }

        return redirect()->route('password.otp.reset')
            ->with('status', 'If this account exists, a 6-digit code has been sent on WhatsApp/email.');
    }

    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('otp_reset_user')) {
            return redirect()->route('password.otp.request');
        }

        return Inertia::render('Auth/ResetPasswordOtp', ['status' => session('status')]);
    }

    public function update(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:'.config('lwe.otp.length')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::find($request->session()->get('otp_reset_user'));

        if (! $user || ! $otp->verify($user, 'password_reset', (string) $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'The code is wrong or has expired.']);
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'current_session_id' => 'reset-'.Str::random(32), // logs out every device
        ])->save();

        $request->session()->forget('otp_reset_user');

        return redirect()->route('login')->with('status', 'Password changed. Please log in.');
    }

    private function findUser(string $login): ?User
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return User::where('email', Str::lower($login))->first();
        }
        $phone = Phone::normalize($login);

        return $phone ? User::where('phone', $phone)->first() : null;
    }
}
