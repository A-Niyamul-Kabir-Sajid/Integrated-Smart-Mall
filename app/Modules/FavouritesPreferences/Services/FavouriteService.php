<?php

namespace App\Modules\FavouritesPreferences\Services;

use App\Modules\FavouritesPreferences\Models\FavouriteProduct;
use App\Modules\FavouritesPreferences\Models\FavouriteShop;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saved shops and saved listings of one account.
 *
 * Saving is idempotent (the composite keys allow one row per target) and only
 * targets a customer can currently see may be saved. Removing is idempotent
 * too. Targets that are later closed or archived stay saved and are reported
 * as unavailable, so the customer decides when to remove them.
 */
class FavouriteService
{
    public function __construct(
        private DirectoryLookup $directory,
        private CatalogueLookup $catalogue,
    ) {}

    /**
     * The listed shop (active or closed, in an active mall) with this id, or null.
     */
    public function saveableShop(string $shopId): ?Shop
    {
        $shop = $this->directory->shop($shopId);

        return $shop !== null && $this->isShopListed($shop) ? $shop : null;
    }

    /**
     * The listing with this id when customers can see it in its mall, or null.
     */
    public function saveableListing(string $listingId): ?ShopProduct
    {
        $mall = ShopProduct::query()->find($listingId)?->shop?->mall;

        if ($mall === null || $mall->status !== MallStatus::Active) {
            return null;
        }

        return $this->catalogue->visibleListing($mall, $listingId);
    }

    public function saveShop(string $userId, Shop $shop): void
    {
        FavouriteShop::query()->insertOrIgnore([
            'user_id' => $userId,
            'shop_id' => $shop->getKey(),
            'created_at' => now(),
        ]);
    }

    public function removeShop(string $userId, string $shopId): void
    {
        FavouriteShop::query()->where('user_id', $userId)->where('shop_id', $shopId)->delete();
    }

    public function saveListing(string $userId, ShopProduct $listing): void
    {
        FavouriteProduct::query()->insertOrIgnore([
            'user_id' => $userId,
            'shop_product_id' => $listing->getKey(),
            'created_at' => now(),
        ]);
    }

    public function removeListing(string $userId, string $listingId): void
    {
        FavouriteProduct::query()->where('user_id', $userId)->where('shop_product_id', $listingId)->delete();
    }

    public function hasSavedShop(string $userId, string $shopId): bool
    {
        return FavouriteShop::query()->where('user_id', $userId)->where('shop_id', $shopId)->exists();
    }

    /**
     * Which of the given listings the account has saved.
     *
     * @param  list<string>  $listingIds
     * @return list<string>
     */
    public function savedListingIds(string $userId, array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        return FavouriteProduct::query()
            ->where('user_id', $userId)
            ->whereIn('shop_product_id', $listingIds)
            ->pluck('shop_product_id')
            ->all();
    }

    /**
     * The account's saved shops, newest first, each with an `is_available` flag.
     *
     * @return Collection<int, FavouriteShop>
     */
    public function savedShops(string $userId): Collection
    {
        return FavouriteShop::query()
            ->where('user_id', $userId)
            ->with(['shop.mall', 'shop.category'])
            ->latest('created_at')
            ->get()
            ->each(fn (FavouriteShop $favourite) => $favourite->setAttribute('is_available', $this->isShopListed($favourite->shop)));
    }

    /**
     * The account's saved listings, newest first, each with an `is_available` flag
     * that is true while customers can still buy the listing in its mall.
     *
     * @return Collection<int, FavouriteProduct>
     */
    public function savedListings(string $userId): Collection
    {
        return FavouriteProduct::query()
            ->where('user_id', $userId)
            ->with(['listing.variant.product', 'listing.shop.mall'])
            ->latest('created_at')
            ->get()
            ->each(fn (FavouriteProduct $favourite) => $favourite->setAttribute('is_available', $this->isListingBuyable($favourite->listing)));
    }

    private function isShopListed(Shop $shop): bool
    {
        return in_array($shop->status, [ShopStatus::Active, ShopStatus::Closed], true)
            && $shop->mall?->status === MallStatus::Active;
    }

    private function isListingBuyable(ShopProduct $listing): bool
    {
        return $listing->status === 'active'
            && $listing->variant->status === 'active'
            && $listing->variant->product->status === 'active'
            && $listing->shop->status === ShopStatus::Active
            && $listing->shop->mall->status === MallStatus::Active;
    }
}
