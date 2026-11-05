<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /** Bcrypt of a random value, checked when no account matches. */
    private const TIMING_DUMMY_HASH = '$2y$12$Jn8v5T.SGTo8juEkXlWMYelHddPhALvNqKp/I6PG7W6J0dOkvrfn2';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        // Only active accounts can sign in. The hash check always runs, so that response
        // time does not reveal whether an email address is registered.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', Str::lower((string) $request->input('email')))->first();
            $passwordMatches = Hash::check((string) $request->input('password'), $user->password ?? self::TIMING_DUMMY_HASH);

            return $passwordMatches && $user?->status === AccountStatus::Active ? $user : null;
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
