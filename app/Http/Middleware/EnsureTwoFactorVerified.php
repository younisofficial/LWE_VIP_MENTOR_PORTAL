<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks admin pages until the login code (2-step) is entered. */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->session()->get('2fa_pending')
            && ! $request->routeIs('two-factor.*', 'logout')) {
            return redirect()->route('two-factor.show');
        }

        return $next($request);
    }
}
