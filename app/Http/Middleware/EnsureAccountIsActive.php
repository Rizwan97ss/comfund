<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isActive() !== true) {
            abort(403, 'Your account is not active. Please contact an administrator.');
        }

        return $next($request);
    }
}
