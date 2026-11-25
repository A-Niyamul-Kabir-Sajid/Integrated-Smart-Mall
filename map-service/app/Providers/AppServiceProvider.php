<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('api-key', function (Request $request): Limit {
            $key = $request->bearerToken() ?? $request->header('X-Api-Key');

            return Limit::perMinute((int) config('mapservice.api_per_minute', 120))
                ->by(is_string($key) && $key !== '' ? 'key:'.hash('sha256', $key) : 'ip:'.$request->ip());
        });

        RateLimiter::for('embed', fn (Request $request): Limit => Limit::perMinute(60)->by('embed:'.$request->ip()));
    }
}
