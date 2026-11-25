<?php

namespace App\Modules\MapsNavigation\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\MapsNavigation\Adapters\HttpMapServiceGateway;
use App\Modules\MapsNavigation\Console\CheckMapService;
use App\Modules\MapsNavigation\Contracts\MapServiceGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Registers MapsNavigation: the client of the separate map service, which
 * owns maps, the navigation graph, QR checkpoints and routes (decisions
 * D-020 and D-021), and the visitor's map pages. The module has no tables.
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

        // Each map page view asks the map service for a new embed link.
        RateLimiter::for('maps', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
