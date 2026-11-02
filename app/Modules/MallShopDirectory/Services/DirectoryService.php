<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Public, read-only directory queries for one mall. Customers only ever see
 * listed shops (active or closed) of the mall they are browsing.
 */
class DirectoryService
{
    public const PER_PAGE = 12;

    public function __construct(private OpeningStatusService $openingStatus) {}

    /**
     * One page of listed shops, alphabetical, each with an `is_open_now` attribute.
     *
     * @param  array{category?: string|null, floor?: string|null, open_now?: bool}  $filters
     * @return LengthAwarePaginator<int, Shop>
     */
    public function browseShops(Mall $mall, array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = $this->listedShops($mall)
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->whereHas(
                'category',
                fn (Builder $category) => $category->where('slug', $slug),
            ))
            ->when($filters['floor'] ?? null, fn (Builder $query, string $code) => $query->whereHas(
                'units',
                fn (Builder $unit) => $unit
                    ->where('status', '!=', 'archived')
                    ->whereHas('floor', fn (Builder $floor) => $floor->where('code', $code)),
            ));

        if ($filters['open_now'] ?? false) {
            $query->whereIn('shop_id', $this->openShopIds($mall));
        }

        $page = $query
            ->with(['category', 'units.floor', 'openingHours'])
            ->orderBy('name')
            ->orderBy('shop_id')
            ->paginate($perPage)
            ->withQueryString();

        $this->markOpenNow($page->getCollection(), $mall);

        return $page;
    }

    /**
     * Ids of the mall's listed shops that are open right now.
     *
     * @return list<string>
     */
    public function openShopIds(Mall $mall): array
    {
        $now = Date::now();

        return $this->listedShops($mall)
            ->where('status', ShopStatus::Active->value)
            ->with('openingHours')
            ->get(['shop_id'])
            ->filter(fn (Shop $shop): bool => $this->openingStatus->isOpenAt($shop->openingHours, $now, $mall->timezone))
            ->pluck('shop_id')
            ->values()
            ->all();
    }

    /**
     * @return Builder<Shop>
     */
    private function listedShops(Mall $mall): Builder
    {
        return Shop::query()->whereBelongsTo($mall)->listed();
    }

    /**
     * @param  iterable<Shop>  $shops
     */
    private function markOpenNow(iterable $shops, Mall $mall): void
    {
        $now = Date::now();

        foreach ($shops as $shop) {
            $shop->setAttribute('is_open_now', $shop->status === ShopStatus::Active
                && $this->openingStatus->isOpenAt($shop->openingHours, $now, $mall->timezone));
        }
    }
}
