<?php

namespace App\Modules\ProductsCatalogue\Services;

use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\Product;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class ProductDiscoveryService implements CatalogueLookup
{
    /**
     * Sort keys accepted by searchProducts().
     *
     * @var list<string>
     */
    public const SORTS = ['name', 'price_asc', 'price_desc'];

    public function __construct(private DirectoryLookup $directory) {}

    /**
     * @param  array{q?: string|null, category?: string|null, shop?: string|null, min_price?: numeric-string|float|null, max_price?: numeric-string|float|null, available_only?: bool, sort?: string|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchProducts(Mall $mall, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $shopIds = $this->directory->operationalShopIds($mall->getKey());
        $offers = fn (Builder|HasManyThrough $query) => $this->visibleOffers($query, $shopIds, $filters);

        $query = Product::query()
            ->active()
            ->whereHas('listings', $offers)
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('category', fn (Builder $category) => $category->where('slug', $slug)))
            ->tap(fn (Builder $query) => $this->applyKeywords($query, (string) ($filters['q'] ?? '')))
            ->withMin(['listings as min_price' => $offers], 'price_amount')
            ->withCount(['listings as offer_count' => $offers])
            ->with('category');

        match ($filters['sort'] ?? 'name') {
            'price_asc' => $query->orderBy('min_price'),
            'price_desc' => $query->orderByDesc('min_price'),
            default => $query->orderBy('name'),
        };

        return $query->orderBy('products.product_id')->paginate($perPage)->withQueryString();
    }

    /**
     * @return Collection<int, ShopProduct>
     */
    public function offersFor(Mall $mall, Product $product): Collection
    {
        return $this->visibleOffers(ShopProduct::query(), $this->directory->operationalShopIds($mall->getKey()), [])
            ->whereHas('variant', fn (Builder $variant) => $variant->where('product_id', $product->getKey()))
            ->with(['variant', 'shop.units.floor'])
            ->orderBy('price_amount')
            ->orderBy('shop_product_id')
            ->get();
    }

    public function visibleListing(Mall $mall, string $listingId): ?ShopProduct
    {
        return $this->visibleOffers(ShopProduct::query(), $this->directory->operationalShopIds($mall->getKey()), [])
            ->whereKey($listingId)
            ->whereHas('variant.product', fn (Builder $product) => $product->where('status', 'active'))
            ->with(['variant.product', 'shop.units.floor'])
            ->first();
    }

    /**
     * Active listings of active variants in the given shops, narrowed by the price and availability filters.
     *
     * @param  list<string>  $shopIds
     * @param  array<string, mixed>  $filters
     */
    private function visibleOffers(Builder|HasManyThrough $query, array $shopIds, array $filters): Builder|HasManyThrough
    {
        $query->where('shop_products.status', 'active')
            ->whereIn('shop_products.shop_id', $shopIds)
            ->when(filled($filters['min_price'] ?? null), fn ($query) => $query->where('shop_products.price_amount', '>=', $filters['min_price']))
            ->when(filled($filters['max_price'] ?? null), fn ($query) => $query->where('shop_products.price_amount', '<=', $filters['max_price']))
            ->when($filters['available_only'] ?? false, fn ($query) => $query->where('shop_products.availability_status', 'available'))
            ->when(filled($filters['shop'] ?? null), fn ($query) => $query->whereHas('shop', fn (Builder $shop) => $shop->where('slug', $filters['shop'])));

        return $query instanceof HasManyThrough
            ? $query->where('product_variants.status', 'active')
            : $query->whereHas('variant', fn (Builder $variant) => $variant->where('status', 'active'));
    }

    /**
     * Every word must appear in the product name, brand or category name; LIKE wildcards match literally.
     */
    private function applyKeywords(Builder $query, string $search): void
    {
        $words = array_slice(preg_split('/\s+/u', Str::lower(trim($search)), -1, PREG_SPLIT_NO_EMPTY), 0, 5);

        foreach ($words as $word) {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';

            $query->where(fn (Builder $match) => $match
                ->whereRaw("lower(products.name) like ? escape '\\'", [$pattern])
                ->orWhereRaw("lower(coalesce(products.brand, '')) like ? escape '\\'", [$pattern])
                ->orWhereHas('category', fn (Builder $category) => $category->whereRaw("lower(name) like ? escape '\\'", [$pattern])));
        }
    }
}
