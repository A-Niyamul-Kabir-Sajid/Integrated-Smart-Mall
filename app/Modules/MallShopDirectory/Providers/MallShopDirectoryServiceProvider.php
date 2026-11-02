<?php

namespace App\Modules\MallShopDirectory\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Registers MallShopDirectory: malls, floors, shops and the public directory.
 */
class MallShopDirectoryServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Public pages address malls by slug and only ever resolve active malls.
        Route::bind('publicMall', fn (string $slug): Mall => Mall::query()->active()->where('slug', $slug)->firstOrFail());

        // A public shop is found by slug only within the mall in the URL, and only while listed.
        Route::bind('publicShop', function (string $slug, RoutingRoute $route): Shop {
            $mall = $route->parameter('publicMall');

            return Shop::query()
                ->whereBelongsTo($mall instanceof Mall ? $mall : Mall::query()->active()->where('slug', $mall)->firstOrFail())
                ->listed()
                ->where('slug', $slug)
                ->firstOrFail();
        });
    }
}
