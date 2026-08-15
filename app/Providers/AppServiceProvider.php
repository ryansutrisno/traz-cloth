<?php

namespace App\Providers;

use App\Listeners\MergeGuestCart;
use App\Services\WhatsApp\FonnteWhatsAppService;
use App\Services\WhatsAppService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppService::class, FonnteWhatsAppService::class);
        $this->app->singleton(LoginResponse::class, function (): LoginResponse {
            return new class implements LoginResponse
            {
                public function toResponse($request): RedirectResponse
                {
                    $redirect = $request->input('redirect');

                    if (is_string($redirect)
                        && Str::startsWith($redirect, '/')
                        && ! Str::startsWith($redirect, '//')) {
                        return redirect($redirect);
                    }

                    return redirect()->intended(route('dashboard'));
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, MergeGuestCart::class);
        $this->configureDefaults();
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
