<?php

namespace App\Modules\FavouritesPreferences\Services;

use App\Modules\FavouritesPreferences\Contracts\PreferenceLookup;
use App\Modules\FavouritesPreferences\DTOs\PreferenceData;
use App\Modules\FavouritesPreferences\Models\UserPreference;
use App\Modules\SharedPlatform\Services\AuditLogger;

/**
 * Reads and stores account-level preferences. Users without a stored row get
 * the application defaults; the first change creates the row.
 */
class PreferenceService implements PreferenceLookup
{
    public function __construct(private AuditLogger $audit) {}

    public function preferencesFor(?string $userId): PreferenceData
    {
        $preference = $userId === null ? new UserPreference : $this->forUser($userId);

        return new PreferenceData(
            locale: $preference->locale,
            routeMode: $preference->route_mode,
            defaultMallId: $preference->default_mall_id,
            arPreferred: $preference->ar_preferred,
            personalizationOptIn: $preference->personalization_opt_in,
        );
    }

    public function forUser(string $userId): UserPreference
    {
        return UserPreference::query()->find($userId) ?? new UserPreference(['user_id' => $userId]);
    }

    /**
     * @param  array<string, mixed>  $changes  Keys of UserPreference::DEFAULTS
     */
    public function update(string $userId, array $changes): UserPreference
    {
        $preference = $this->forUser($userId);
        $preference->fill(array_intersect_key($changes, UserPreference::DEFAULTS));

        $changed = array_keys(array_intersect_key($preference->getDirty(), UserPreference::DEFAULTS));

        if ($preference->exists && $changed === []) {
            return $preference;
        }

        $preference->save();

        $this->audit->record('preferences.updated', $preference, ['fields' => $changed]);

        return $preference;
    }
}
