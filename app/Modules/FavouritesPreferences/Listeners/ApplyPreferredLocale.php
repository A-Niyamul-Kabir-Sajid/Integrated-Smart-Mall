<?php

namespace App\Modules\FavouritesPreferences\Listeners;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Services\PreferenceService;
use App\Modules\SharedPlatform\Services\LocaleService;
use Illuminate\Auth\Events\Login;

/**
 * Switches the session to the user's preferred language when they sign in.
 */
class ApplyPreferredLocale
{
    public function __construct(
        private PreferenceService $preferences,
        private LocaleService $locales,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! app()->bound('session.store')) {
            return;
        }

        $this->locales->remember(app('session.store'), $this->preferences->forUser($event->user->getKey())->locale);
    }
}
