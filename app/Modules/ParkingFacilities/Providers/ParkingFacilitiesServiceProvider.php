<?php

namespace App\Modules\ParkingFacilities\Providers;

use App\Core\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Registers ParkingFacilities: the facility finder, saved parking and the mall
 * administrators' facility and parking-zone screens (decision D-013).
 */
class ParkingFacilitiesServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('parking', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
