<?php

namespace App\Modules\ShopOwnerOperations\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Services\ShopAccessService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->defineAreaAbilities();

        RateLimiter::for('shop-applications', fn (Request $request): Limit => Limit::perHour(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('staff-invitations', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * One ability per workspace area (shop-area-stock, shop-area-orders, ...). The decision
     * follows the member's work function and requires an active shop (decision D-014).
     */
    private function defineAreaAbilities(): void
    {
        foreach (ShopArea::cases() as $area) {
            Gate::define($area->ability(), fn (User $user, Shop $shop) => app(ShopAccessService::class)->check($user, $shop, $area));
        }
    }
}
