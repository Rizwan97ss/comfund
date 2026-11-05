<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RecordRequestAudit;
use App\Http\Middleware\RespondIfAuthenticated;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\ThrottleSensitiveAuthEndpoints;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->append([
            SetSecurityHeaders::class,
            RecordRequestAudit::class,
        ]);

        $middleware->web(append: [ThrottleSensitiveAuthEndpoints::class]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'active' => EnsureAccountIsActive::class,
            'guest' => RespondIfAuthenticated::class,
        ]);

        // Refuse by role before resolving route models, so that a member's 403 or 404
        // never reveals whether a record exists.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureRole::class);
        $middleware->prependToPriorityList(EnsureRole::class, EnsureEmailIsVerified::class);
        $middleware->prependToPriorityList(EnsureEmailIsVerified::class, EnsureAccountIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The default message names the model class and id, which are internal details.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (($request->is('api/*') || $request->expectsJson()) && $exception->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            return null;
        });
    })->create();
