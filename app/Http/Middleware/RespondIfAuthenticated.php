<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A signed-in browser posting to a guest route, such as the login form in a stale tab,
 * would be redirected to an HTML page. An API client needs an answer it can read.
 */
class RespondIfAuthenticated extends RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ($request->expectsJson()) {
            foreach ($guards === [] ? [null] : $guards as $guard) {
                if (Auth::guard($guard)->check()) {
                    return response()->json(['two_factor' => false]);
                }
            }
        }

        return parent::handle($request, $next, ...$guards);
    }
}
