<?php

namespace App\Providers;

use App\Support\Tenancy\StoreContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(StoreContext::class, fn () => new StoreContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if ($publicPath = config('app.public_path')) {
            $this->app->usePublicPath($publicPath);
        }

        foreach ([TwoFactorAuthenticationConfirmed::class, ValidTwoFactorAuthenticationCodeProvided::class] as $event) {
            Event::listen($event, function ($event): void {
                if (request()->hasSession()) {
                    request()->session()->put('platform.mfa', [
                        'user_id' => (string) $event->user->getKey(),
                        'fingerprint' => hash('sha256', (string) $event->user->two_factor_secret),
                    ]);
                }
            });
        }
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
}
