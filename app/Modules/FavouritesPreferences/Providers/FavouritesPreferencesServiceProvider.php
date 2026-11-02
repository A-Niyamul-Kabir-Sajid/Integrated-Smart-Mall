<?php

namespace App\Modules\FavouritesPreferences\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\FavouritesPreferences\Listeners\ApplyPreferredLocale;
use App\Modules\FavouritesPreferences\Services\UserNotificationPreferences;
use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use Illuminate\Auth\Events\Login;

/**
 * Registers FavouritesPreferences: account preferences (and favourites later).
 * Listed after SharedPlatform in config/modules.php so its notification
 * preferences replace the platform defaults.
 */
class FavouritesPreferencesServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        Login::class => [ApplyPreferredLocale::class],
    ];

    public function register(): void
    {
        $this->app->bind(NotificationPreferences::class, UserNotificationPreferences::class);
    }
}
