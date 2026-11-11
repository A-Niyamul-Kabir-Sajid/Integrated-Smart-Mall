<?php

namespace App\Modules\FavouritesPreferences\Contracts;

use App\Modules\FavouritesPreferences\DTOs\PreferenceData;

/**
 * Read-only preferences for other modules. Changes go through PreferenceService
 * and the account's own preferences page.
 *
 * Consumers: MapsNavigation (route mode, step-free routes, AR) and the customer
 * pages (usual mall). Notification choices have their own contract,
 * SharedPlatform\Contracts\NotificationPreferences.
 */
interface PreferenceLookup
{
    /**
     * The visitor's preferences; null means a guest and yields the defaults.
     */
    public function preferencesFor(?string $userId): PreferenceData;
}
