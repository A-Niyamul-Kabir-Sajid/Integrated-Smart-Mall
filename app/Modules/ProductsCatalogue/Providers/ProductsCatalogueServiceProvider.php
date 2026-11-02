<?php

namespace App\Modules\ProductsCatalogue\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Services\ProductDiscoveryService;

/**
 * Registers ProductsCatalogue: shared products, shop listings and customer discovery.
 */
class ProductsCatalogueServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CatalogueLookup::class, ProductDiscoveryService::class);
    }
}
