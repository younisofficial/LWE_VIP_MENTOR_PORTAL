<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\DeviceGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * One account = one active device.
 * Each login stores a random token in the session and in users.current_session_id.
 * Logging in somewhere else replaces the token, so the older browser is logged out on its next request.
 */
class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isSuspended()) {
            return $this->logout($request, 'Your account is suspended. Please contact support.');
        }

        $token = $request->session()->get(DeviceGuard::SESSION_KEY);

        if ($user->current_session_id === null) {
            // Logged in before this feature existed: adopt this browser.
            $token = Str::random(40);
            $request->session()->put(DeviceGuard::SESSION_KEY, $token);
            $user->forceFill(['current_session_id' => $token])->save();
        } elseif (! is_string($token) || ! hash_equals($user->current_session_id, $token)) {
            return $this->logout($request, 'You logged in on another device, so you were logged out here.');
        }

        return $next($request);
    }

    private function logout(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('login')->with('status', $message);
    }
}
