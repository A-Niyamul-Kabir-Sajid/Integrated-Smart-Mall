<?php

namespace App\Modules\FavouritesPreferences\DTOs;

use App\Modules\FavouritesPreferences\Enums\RouteMode;

/**
 * The preferences another module may apply for one visitor: the stored values
 * for a signed-in account, the application defaults for guests and for accounts
 * that never saved preferences.
 */
final readonly class PreferenceData
{
    public function __construct(
        public string $locale,
        public RouteMode $routeMode,
        public ?string $defaultMallId,
        public bool $arPreferred,
        public bool $personalizationOptIn,
    ) {}

    /**
     * Whether routes should avoid stairs and escalators (the step-free route mode).
     */
    public function needsStepFreeRoutes(): bool
    {
        return $this->routeMode === RouteMode::AccessibleDistance;
    }
}
