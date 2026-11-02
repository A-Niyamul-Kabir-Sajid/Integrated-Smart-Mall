<?php

namespace App\Modules\ShopOwnerOperations\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Registers ShopOwnerOperations: the shop workspace, which calls the owning
 * modules' services on behalf of authorised shop staff.
 */
class ShopOwnerOperationsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // A listing in a workspace URL must belong to the shop in the same URL; otherwise 404.
        Route::bind('shopListing', function (string $id, RoutingRoute $route): ShopProduct {
            $shop = $route->parameter('shop');

            abort_unless(Str::isUuid($id), 404);

            return ShopProduct::query()
                ->whereKey($id)
                ->where('shop_id', $shop instanceof Shop ? $shop->getKey() : $shop)
                ->firstOrFail();
        });
    }
}
