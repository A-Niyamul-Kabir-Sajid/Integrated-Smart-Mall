<?php

namespace App\Modules\ProductsCatalogue\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Services\ProductDiscoveryService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Registers ProductsCatalogue: shared products, shop listings and customer discovery.
 */
class ProductsCatalogueServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CatalogueLookup::class, ProductDiscoveryService::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Public product pages resolve active products by id; malformed ids are 404 before any query.
        Route::bind('publicProduct', function (string $id): Product {
            abort_unless(Str::isUuid($id), 404);

            return Product::query()->active()->findOrFail($id);
        });
    }
}
