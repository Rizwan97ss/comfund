<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify throttles login and two-factor challenges itself; this covers the rest.
 */
class ThrottleSensitiveAuthEndpoints
{
    private const PATHS = ['forgot-password', 'reset-password', 'email/verification-notification', 'user/confirm-password', 'user/password'];

    private const MAX_ATTEMPTS = 6;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! in_array($request->path(), self::PATHS, true)) {
            return $next($request);
        }

        $key = 'auth-endpoint:'.$request->path().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            abort(429, 'Too many attempts. Please wait a minute and try again.');
        }

        RateLimiter::hit($key);

        return $next($request);
    }
}
