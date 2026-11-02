<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Public, read-only directory queries for one mall. Customers only ever see
 * listed shops (active or closed) of the mall they are browsing.
 */
class DirectoryService
{
    public const PER_PAGE = 12;

    public function __construct(private OpeningStatusService $openingStatus) {}

    /**
     * Sort keys accepted by browseShops().
     *
     * @var list<string>
     */
    public const SORTS = ['name', 'name_desc', 'newest'];

    /**
     * One page of listed shops, each with an `is_open_now` attribute.
     *
     * @param  array{q?: string|null, category?: string|null, floor?: string|null, open_now?: bool, sort?: string|null}  $filters
     * @return LengthAwarePaginator<int, Shop>
     */
    public function browseShops(Mall $mall, array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = $this->listedShops($mall)
            ->tap(fn (Builder $query) => $this->applySearch($query, (string) ($filters['q'] ?? '')))
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

        match ($filters['sort'] ?? 'name') {
            'name_desc' => $query->orderByDesc('name'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderBy('name'),
        };

        $page = $query
            ->with(['category', 'units.floor', 'openingHours'])
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
     * Normalised keyword search: case-insensitive, whitespace-collapsed, at most
     * five words, every word must appear in the name, description or category
     * name. LIKE wildcards typed by the user match literally.
     *
     * @param  Builder<Shop>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $words = array_slice(preg_split('/\s+/u', Str::lower(trim($search)), -1, PREG_SPLIT_NO_EMPTY), 0, 5);

        foreach ($words as $word) {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';

            $query->where(fn (Builder $match) => $match
                ->whereRaw("lower(name) like ? escape '\\'", [$pattern])
                ->orWhereRaw("lower(coalesce(description, '')) like ? escape '\\'", [$pattern])
                ->orWhereHas('category', fn (Builder $category) => $category->whereRaw("lower(name) like ? escape '\\'", [$pattern])));
        }
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
