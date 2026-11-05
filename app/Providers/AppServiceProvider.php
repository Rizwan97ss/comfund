<?php

namespace App\Providers;

use App\Listeners\AuditSystemEvents;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureAuditing();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        if (filled(config('fund.trusted_proxies'))) {
            TrustProxies::at((string) config('fund.trusted_proxies'));
        }

        URL::forceHttps(app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('financial-writes', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('documents', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }

    protected function configureAuditing(): void
    {
        Event::subscribe(AuditSystemEvents::class);

        Gate::after(function (User $user, string $ability, mixed $result, array $arguments): void {
            $denied = $result === false || ($result instanceof Response && $result->denied());

            if ($denied) {
                $target = $arguments[0] ?? null;

                app(AuditLogger::class)->record('security.authorization_denied', 'security', 'warning', payload: [
                    'ability' => $ability,
                    'target' => $target instanceof Model ? class_basename($target).':'.$target->getKey() : (is_object($target) ? class_basename($target) : $target),
                ]);
            }
        });
    }
}
