<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Second login step for Admin / Super Admin. */
class TwoFactorController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->get('2fa_pending')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/TwoFactor');
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:'.config('lwe.otp.length')]]);

        if (! $otp->verify($request->user(), 'two_factor', (string) $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'The code is wrong or has expired.']);
        }

        $request->session()->forget('2fa_pending');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $key = '2fa-resend:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['code' => 'Please wait before asking for another code.']);
        }
        RateLimiter::hit($key, 300);
        $otp->issue($request->user(), 'two_factor');

        return back()->with('status', 'A new code has been sent.');
    }
}
