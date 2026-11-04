<?php

namespace App\Modules\ProductsCatalogue\Contracts;

use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only catalogue facts for customers and other modules. Only "visible"
 * offers count: active listings of active products and variants, sold by the
 * mall's operational shops. Prices and availability are owner-maintained.
 *
 * Consumers: the customer pages, FavouritesPreferences (a listing by id),
 * MapsNavigation (the shop of a listing) and ChatbotRag (structured search
 * instead of free text).
 */
interface CatalogueLookup
{
    /**
     * One page of products with at least one visible offer matching the filters,
     * each carrying `min_price` and `offer_count` for those offers.
     *
     * @param  array{q?: string|null, category?: string|null, shop?: string|null, min_price?: numeric-string|float|null, max_price?: numeric-string|float|null, available_only?: bool, sort?: string|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchProducts(Mall $mall, array $filters = [], int $perPage = 12): LengthAwarePaginator;

    /**
     * The product's visible offers in the mall, cheapest first, with shop locations loaded.
     *
     * @return Collection<int, ShopProduct>
     */
    public function offersFor(Mall $mall, Product $product): Collection;

    /**
     * A visible offer in the mall by listing id, or null.
     */
    public function visibleListing(Mall $mall, string $listingId): ?ShopProduct;
}
