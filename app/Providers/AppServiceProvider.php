<?php

namespace App\Providers;

use App\Models\Admin\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
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
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Grant the super-admin role every ability.
     *
     * Returning null rather than false leaves every other check untouched, so
     * a normal user still falls through to policies and permission checks.
     *
     * @see Role::SUPER_ADMIN
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SUPER_ADMIN) ? true : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults($this->passwordPolicy(...));
    }

    /**
     * The application's password policy, assembled from `config/auth.php`.
     *
     * There is no environment branch here, and that is the point. This used to
     * read `app()->isProduction() ? Password::min(12)->… : null`, which meant
     * `Password::default()` fell through to Laravel's bare `min(8)` everywhere
     * else — so the whole test suite exercised a policy the application never
     * enforces, and any test asserting on password strength proved nothing.
     *
     * @see config('auth.password_policy')
     */
    protected function passwordPolicy(): Password
    {
        /** @var array{min_length: int, mixed_case: bool, letters: bool, numbers: bool, symbols: bool, uncompromised: bool} $policy */
        $policy = config('auth.password_policy');

        $rule = Password::min($policy['min_length']);

        if ($policy['mixed_case']) {
            $rule->mixedCase();
        }

        if ($policy['letters']) {
            $rule->letters();
        }

        if ($policy['numbers']) {
            $rule->numbers();
        }

        if ($policy['symbols']) {
            $rule->symbols();
        }

        if ($policy['uncompromised']) {
            $rule->uncompromised();
        }

        return $rule;
    }
}
