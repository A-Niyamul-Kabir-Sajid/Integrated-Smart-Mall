<?php

namespace App\Modules\MallOwnerAdmin\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\MallOwnerAdmin\Models\Announcement;
use App\Modules\MallOwnerAdmin\Models\Complaint;
use App\Modules\MallOwnerAdmin\Models\WorkOrder;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Registers MallOwnerAdmin: the mall administrators' workspace, which calls the owning
 * modules' services on behalf of authorised administrators (decision D-015).
 */
class MallOwnerAdminServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Records in an administration URL must belong to the mall in the same URL; otherwise 404.
        Route::bind('adminComplaint', fn (string $id, RoutingRoute $route): Complaint => $this->scopedToMall(Complaint::query(), $id, $route)->firstOrFail());
        Route::bind('adminWorkOrder', fn (string $id, RoutingRoute $route): WorkOrder => $this->scopedToMall(WorkOrder::query(), $id, $route)->firstOrFail());
        Route::bind('adminAnnouncement', fn (string $id, RoutingRoute $route): Announcement => $this->scopedToMall(Announcement::query(), $id, $route)->firstOrFail());
        Route::bind('adminMaintenance', fn (string $id, RoutingRoute $route): MaintenanceRequest => $this->scopedToMall(MaintenanceRequest::query(), $id, $route)->firstOrFail());

        // A customer reaches only their own complaints; anyone else's is not found.
        Route::bind('customerComplaint', fn (string $id): Complaint => Complaint::query()->whereKey($this->uuid($id))->where('customer_user_id', auth()->id())->firstOrFail());

        RateLimiter::for('mall-admin', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('complaints', fn (Request $request): Limit => Limit::perHour(10)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function scopedToMall(Builder $query, string $id, RoutingRoute $route): Builder
    {
        $mall = $route->parameter('mall');

        return $query->whereKey($this->uuid($id))->where('mall_id', $mall instanceof Mall ? $mall->getKey() : $mall);
    }

    private function uuid(string $id): string
    {
        abort_unless(Str::isUuid($id), 404);

        return $id;
    }
}
