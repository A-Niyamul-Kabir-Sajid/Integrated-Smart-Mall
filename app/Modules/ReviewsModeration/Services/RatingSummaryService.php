<?php

namespace App\Modules\ReviewsModeration\Services;

use App\Modules\ReviewsModeration\Contracts\ReviewSummaryLookup;
use App\Modules\ReviewsModeration\DTOs\RatingSummaryData;
use App\Modules\ReviewsModeration\Models\Review;
use Illuminate\Database\Eloquent\Builder;

/**
 * Calculates rating summaries straight from the published reviews, so a moderation
 * decision or a withdrawal changes every summary immediately and nothing is cached
 * that could drift. Shop reviews and listing reviews are summarised separately.
 */
class RatingSummaryService implements ReviewSummaryLookup
{
    public function forShop(string $shopId): RatingSummaryData
    {
        return $this->forShops([$shopId])[$shopId];
    }

    public function forListing(string $shopProductId): RatingSummaryData
    {
        return $this->forListings([$shopProductId])[$shopProductId];
    }

    public function forShops(array $shopIds): array
    {
        return $this->summarise(Review::query()->published()->whereNull('shop_product_id'), 'shop_id', $shopIds);
    }

    public function forListings(array $shopProductIds): array
    {
        return $this->summarise(Review::query()->published(), 'shop_product_id', $shopProductIds);
    }

    /**
     * @param  Builder<Review>  $base  Published reviews of the kind being summarised
     * @param  'shop_id'|'shop_product_id'  $column
     * @param  list<string>  $ids
     * @return array<string, RatingSummaryData>
     */
    private function summarise(Builder $base, string $column, array $ids): array
    {
        $ids = array_values(array_unique($ids));
        $summaries = array_fill_keys($ids, RatingSummaryData::empty());

        if ($ids === []) {
            return $summaries;
        }

        $counts = (clone $base)->whereIn($column, $ids)
            ->selectRaw("{$column} as target_id, rating, count(*) as total")
            ->groupBy($column, 'rating')
            ->get();

        $verified = (clone $base)->verified()->whereIn($column, $ids)
            ->selectRaw("{$column} as target_id, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'target_id');

        foreach ($counts->groupBy('target_id') as $targetId => $rows) {
            $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
            $sum = 0;
            $count = 0;

            foreach ($rows as $row) {
                $distribution[(int) $row->rating] = (int) $row->total;
                $sum += (int) $row->rating * (int) $row->total;
                $count += (int) $row->total;
            }

            $summaries[$targetId] = new RatingSummaryData($count, round($sum / $count, 2), $distribution, (int) ($verified[$targetId] ?? 0));
        }

        return $summaries;
    }
}
