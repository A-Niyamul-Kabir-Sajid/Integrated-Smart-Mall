<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use Illuminate\Support\Collection;

class DirectoryLookupService implements DirectoryLookup
{
    public function activeMallBySlug(string $slug): ?Mall
    {
        return Mall::query()->active()->where('slug', $slug)->first();
    }

    public function shop(string $shopId): ?Shop
    {
        return Shop::query()->find($shopId);
    }

    public function isShopOperational(string $shopId): bool
    {
        return Shop::query()->whereKey($shopId)->where('status', ShopStatus::Active->value)->exists();
    }

    /**
     * @return list<string>
     */
    public function listedShopIds(string $mallId): array
    {
        return Shop::query()->where('mall_id', $mallId)->listed()->orderBy('shop_id')->pluck('shop_id')->all();
    }

    /**
     * @return list<string>
     */
    public function operationalShopIds(string $mallId): array
    {
        return Shop::query()->where('mall_id', $mallId)->where('status', ShopStatus::Active->value)->orderBy('shop_id')->pluck('shop_id')->all();
    }

    /**
     * @return Collection<int, Floor>
     */
    public function floorsOf(string $mallId): Collection
    {
        return Floor::query()->where('mall_id', $mallId)->active()->orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, ShopUnit>
     */
    public function locationsOf(string $shopId): Collection
    {
        return ShopUnit::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'archived')
            ->with('floor')
            ->orderBy('unit_code')
            ->get();
    }
}
