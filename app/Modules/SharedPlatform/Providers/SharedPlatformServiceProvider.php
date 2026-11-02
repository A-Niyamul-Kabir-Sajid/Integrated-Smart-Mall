<?php

namespace App\Modules\SharedPlatform\Providers;

use App\Core\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Registers SharedPlatform: localization, notifications, audit and the shared security conventions.
 */
class SharedPlatformServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->configureRateLimiters();
    }

    /**
     * Named limiters applied with the throttle middleware, e.g. ->middleware('throttle:login').
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower(trim((string) $request->input('login'))).'|'.$request->ip()));

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
