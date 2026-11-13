<?php

namespace App\Modules\ShopOwnerOperations\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\ShopOwnerOperations\Console\ExpireOverdueOrders;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Models\ShopPromotion;
use App\Modules\ShopOwnerOperations\Models\ShopReturn;
use App\Modules\ShopOwnerOperations\Models\SupportThread;
use App\Modules\ShopOwnerOperations\Services\ShopAccessService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Builder;
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

        // Orders and sales in a workspace URL must belong to the shop in the same URL; otherwise 404.
        Route::bind('shopOrder', fn (string $id, RoutingRoute $route): ShopOrder => $this->scopedToShop(ShopOrder::query(), $id, $route)->firstOrFail());
        Route::bind('shopPurchase', fn (string $id, RoutingRoute $route): Purchase => $this->scopedToShop(Purchase::query(), $id, $route)->firstOrFail());

        Route::bind('shopPromotion', fn (string $id, RoutingRoute $route): ShopPromotion => $this->scopedToShop(ShopPromotion::query(), $id, $route)->firstOrFail());
        Route::bind('shopThread', fn (string $id, RoutingRoute $route): SupportThread => $this->scopedToShop(SupportThread::query(), $id, $route)->firstOrFail());
        Route::bind('customerThread', fn (string $id): SupportThread => SupportThread::query()->whereKey($this->uuid($id))->where('customer_user_id', auth()->id())->firstOrFail());
        Route::bind('shopMaintenance', fn (string $id, RoutingRoute $route): MaintenanceRequest => $this->scopedToShop(MaintenanceRequest::query(), $id, $route)->firstOrFail());
        Route::bind('shopReturn', fn (string $id, RoutingRoute $route): ShopReturn => $this->scopedToShop(ShopReturn::query(), $id, $route)->firstOrFail());

        // A customer reaches only their own orders and purchases; anyone else's is not found.
        Route::bind('customerReturn', fn (string $id): ShopReturn => ShopReturn::query()->whereKey($this->uuid($id))
            ->whereIn('purchase_id', Purchase::query()->where('buyer_user_id', auth()->id())->select('purchase_id'))->firstOrFail());
        Route::bind('customerOrder', fn (string $id): ShopOrder => ShopOrder::query()->whereKey($this->uuid($id))->where('customer_user_id', auth()->id())->firstOrFail());
        Route::bind('customerPurchase', fn (string $id): Purchase => Purchase::query()->whereKey($this->uuid($id))->where('buyer_user_id', auth()->id())->firstOrFail());

        $this->defineAreaAbilities();

        $this->commands([ExpireOverdueOrders::class]);
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command(ExpireOverdueOrders::class)->daily()->onOneServer());

        RateLimiter::for('shop-applications', fn (Request $request): Limit => Limit::perHour(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('orders', fn (Request $request): Limit => Limit::perMinute(20)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('pickup-codes', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('support-messages', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('staff-invitations', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopedToShop($query, string $id, RoutingRoute $route)
    {
        $shop = $route->parameter('shop');

        return $query->whereKey($this->uuid($id))->where('shop_id', $shop instanceof Shop ? $shop->getKey() : $shop);
    }

    private function uuid(string $id): string
    {
        abort_unless(Str::isUuid($id), 404);

        return $id;
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
