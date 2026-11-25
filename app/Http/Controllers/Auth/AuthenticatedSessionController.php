<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Setting;
use App\Services\DeviceGuard;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.otp.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request, DeviceGuard $devices, OtpService $otp): RedirectResponse
    {
        $user = $request->authenticateUser();

        if ($user->isSuspended()) {
            $devices->log($user, $request, 'suspended');
            throw ValidationException::withMessages(['login' => 'Your account is suspended. Please contact support on WhatsApp.']);
        }

        if (! $devices->allows($user, $request)) {
            $devices->log($user, $request, 'blocked_device');
            throw ValidationException::withMessages(['login' => 'This account is locked to another device. Please contact support.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Single active device: this session becomes the only valid one.
        $devices->startSession($user, $request);

        // Admins: second step with a one-time code.
        if ($user->isStaff() && Setting::get('admin_two_factor')) {
            $request->session()->put('2fa_pending', true);
            $otp->issue($user, 'two_factor');

            return redirect()->route('two-factor.show');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->current_session_id === $request->session()->get(DeviceGuard::SESSION_KEY)) {
            $user->forceFill(['current_session_id' => null])->save();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
