<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role?->value !== $role) {
            $this->audit->record('security.role_denied', 'security', 'warning', payload: ['required_role' => $role, 'route' => $request->route()?->uri()]);

            abort(403, 'You do not have permission to do this.');
        }

        return $next($request);
    }
}
