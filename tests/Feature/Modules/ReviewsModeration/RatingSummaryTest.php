<?php

namespace Tests\Feature\Modules\ReviewsModeration;

use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Contracts\ReviewSummaryLookup;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function lookup(): ReviewSummaryLookup
    {
        return app(ReviewSummaryLookup::class);
    }

    public function test_a_target_without_reviews_has_an_empty_summary(): void
    {
        $summary = $this->lookup()->forShop(Shop::factory()->create()->getKey());

        $this->assertSame(0, $summary->reviewCount);
        $this->assertNull($summary->averageRating);
        $this->assertSame([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], $summary->distribution);
        $this->assertSame(0, $summary->verifiedCount);
        $this->assertSame(0, $summary->percentOf(5));
    }

    public function test_the_summary_counts_the_average_and_distribution_of_published_reviews(): void
    {
        $shop = Shop::factory()->create();
        foreach ([5, 5, 4, 2] as $rating) {
            Review::factory()->for($shop)->rated($rating)->create();
        }

        $summary = $this->lookup()->forShop($shop->getKey());

        $this->assertSame(4, $summary->reviewCount);
        $this->assertSame(4.0, $summary->averageRating);
        $this->assertSame([5 => 2, 4 => 1, 3 => 0, 2 => 1, 1 => 0], $summary->distribution);
        $this->assertSame(50, $summary->percentOf(5));
    }

    public function test_the_average_keeps_two_decimals(): void
    {
        $shop = Shop::factory()->create();
        foreach ([5, 4, 4] as $rating) {
            Review::factory()->for($shop)->rated($rating)->create();
        }

        $this->assertSame(4.33, $this->lookup()->forShop($shop->getKey())->averageRating);
    }

    public function test_hidden_withdrawn_and_pending_reviews_are_not_counted(): void
    {
        $shop = Shop::factory()->create();
        Review::factory()->for($shop)->rated(5)->create();
        Review::factory()->for($shop)->rated(1)->hidden()->create();
        Review::factory()->for($shop)->rated(1)->withdrawn()->create();
        Review::factory()->for($shop)->rated(1)->pending()->create();

        $summary = $this->lookup()->forShop($shop->getKey());

        $this->assertSame(1, $summary->reviewCount);
        $this->assertSame(5.0, $summary->averageRating);
    }

    public function test_moderation_and_withdrawal_change_the_summary_at_once(): void
    {
        $shop = Shop::factory()->create();
        Review::factory()->for($shop)->rated(5)->create();
        $abusive = Review::factory()->for($shop)->rated(1)->create();
        $this->assertSame(3.0, $this->lookup()->forShop($shop->getKey())->averageRating);

        $abusive->update(['status' => ReviewStatus::Hidden]);
        $this->assertSame(5.0, $this->lookup()->forShop($shop->getKey())->averageRating);

        $abusive->update(['status' => ReviewStatus::Published]);
        $this->assertSame(3.0, $this->lookup()->forShop($shop->getKey())->averageRating);
    }

    public function test_shop_scores_and_listing_scores_are_never_mixed(): void
    {
        $listing = ShopProduct::factory()->create();
        Review::factory()->for($listing->shop)->rated(5)->create();
        Review::factory()->forListing($listing)->rated(1)->create();

        $shop = $this->lookup()->forShop($listing->shop_id);
        $product = $this->lookup()->forListing($listing->getKey());

        $this->assertSame([1, 5.0], [$shop->reviewCount, $shop->averageRating]);
        $this->assertSame([1, 1.0], [$product->reviewCount, $product->averageRating]);
    }

    public function test_verified_reviews_are_counted_separately_and_lose_the_badge_when_revoked(): void
    {
        $listing = ShopProduct::factory()->create();
        Review::factory()->forListing($listing)->create();
        $verified = Review::factory()->forListing($listing)->verified()->create();

        $this->assertSame(1, $this->lookup()->forListing($listing->getKey())->verifiedCount);
        $this->assertSame(2, $this->lookup()->forListing($listing->getKey())->reviewCount);

        $verified->verification->update(['revoked_at' => now()]);
        $this->assertSame(0, $this->lookup()->forListing($listing->getKey())->verifiedCount);
        $this->assertSame(2, $this->lookup()->forListing($listing->getKey())->reviewCount);
    }

    public function test_bulk_lookup_returns_every_requested_target_in_few_queries(): void
    {
        $shops = Shop::factory()->count(3)->create();
        Review::factory()->for($shops[0])->rated(4)->create();
        Review::factory()->for($shops[1])->rated(2)->create();
        $ids = $shops->map->getKey()->all();

        $queries = 0;
        \DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $summaries = $this->lookup()->forShops([...$ids, $ids[0]]);

        $this->assertSame(2, $queries);
        $this->assertEqualsCanonicalizing($ids, array_keys($summaries));
        $this->assertSame(4.0, $summaries[$ids[0]]->averageRating);
        $this->assertSame(2.0, $summaries[$ids[1]]->averageRating);
        $this->assertSame(0, $summaries[$ids[2]]->reviewCount);
        $this->assertSame([], $this->lookup()->forShops([]));
    }
}
