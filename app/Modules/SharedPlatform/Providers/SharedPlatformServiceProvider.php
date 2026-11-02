<?php

namespace App\Modules\SharedPlatform\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\SharedPlatform\Adapters\LogSmsGateway;
use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use App\Modules\SharedPlatform\Contracts\SmsGateway;
use App\Modules\SharedPlatform\Notifications\Channels\SmsChannel;
use App\Modules\SharedPlatform\Services\DefaultNotificationPreferences;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Registers SharedPlatform: localization, notifications, audit and the shared security conventions.
 */
class SharedPlatformServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, LogSmsGateway::class);
        $this->app->bind(NotificationPreferences::class, DefaultNotificationPreferences::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->configureSecureDefaults();
        $this->configureRateLimiters();

        Notification::extend('sms', fn ($app): SmsChannel => $app->make(SmsChannel::class));
    }

    /**
     * Framework-wide defaults: strong passwords, strict Eloquent outside production,
     * HTTPS links and no destructive database commands in production.
     */
    private function configureSecureDefaults(): void
    {
        $isProduction = $this->app->isProduction();

        Password::defaults(function () use ($isProduction): Password {
            $rule = Password::min(8)->letters()->numbers();

            return $isProduction ? $rule->uncompromised() : $rule;
        });

        Model::preventLazyLoading(! $isProduction);
        Model::preventSilentlyDiscardingAttributes(! $isProduction);

        URL::forceHttps($isProduction);
        DB::prohibitDestructiveCommands($isProduction);
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
