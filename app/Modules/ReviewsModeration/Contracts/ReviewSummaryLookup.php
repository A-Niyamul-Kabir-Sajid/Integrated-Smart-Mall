<?php

namespace App\Modules\ReviewsModeration\Contracts;

use App\Modules\ReviewsModeration\DTOs\RatingSummaryData;

/**
 * Rating summaries for other modules (directory, catalogue, analytics, the assistant).
 *
 * Only published reviews count, so a hidden or withdrawn review leaves every summary at
 * once. Shop scores and product-listing scores are separate: forShop() counts reviews of
 * the shop itself, forListing() counts reviews of that one listing, and neither is
 * mixed into the other.
 */
interface ReviewSummaryLookup
{
    public function forShop(string $shopId): RatingSummaryData;

    public function forListing(string $shopProductId): RatingSummaryData;

    /**
     * @param  list<string>  $shopIds
     * @return array<string, RatingSummaryData> Keyed by shop id; every requested id is present
     */
    public function forShops(array $shopIds): array;

    /**
     * @param  list<string>  $shopProductIds
     * @return array<string, RatingSummaryData> Keyed by listing id; every requested id is present
     */
    public function forListings(array $shopProductIds): array;
}
