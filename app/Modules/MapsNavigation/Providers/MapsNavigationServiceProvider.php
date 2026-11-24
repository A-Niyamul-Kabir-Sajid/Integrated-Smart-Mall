<?php

namespace App\Modules\MapsNavigation\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\MapsNavigation\Adapters\HttpMapServiceGateway;
use App\Modules\MapsNavigation\Console\CheckMapService;
use App\Modules\MapsNavigation\Contracts\MapServiceGateway;

/**
 * Registers MapsNavigation. For now it connects to the separate map service
 * (decision D-020); the module's own tables come with ISM-59.
 */
class MapsNavigationServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MapServiceGateway::class, fn (): MapServiceGateway => new HttpMapServiceGateway((array) config('services.map_service')));
    }

    public function boot(): void
    {
        parent::boot();

        $this->commands([CheckMapService::class]);
    }
}
