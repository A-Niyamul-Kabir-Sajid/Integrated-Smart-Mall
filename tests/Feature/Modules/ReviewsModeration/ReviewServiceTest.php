<?php

namespace Tests\Feature\Modules\ReviewsModeration;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\TokenRedemption;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewVerification;
use App\Modules\ReviewsModeration\Services\ReviewService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewServiceTest extends TestCase
{
    use CreatesReviewFixtures, RefreshDatabase;

    private function service(): ReviewService
    {
        return app(ReviewService::class);
    }

    public function test_a_customer_reviews_a_shop_and_it_is_published_at_once(): void
    {
        $shop = Shop::factory()->create();
        $author = User::factory()->create();

        $review = $this->service()->submitShopReview($author, $shop, [...$this->goodReview, 'title' => '  ']);

        $this->assertSame(ReviewStatus::Published, $review->status);
        $this->assertSame(4, $review->rating);
        $this->assertNull($review->title);
        $this->assertTrue($review->isShopReview());
        $this->assertFalse($review->isVerified());
        $this->assertSame(['review.submitted'], AuditLog::query()->where('action', 'review.submitted')->pluck('action')->all());
        $this->assertStringNotContainsString('Friendly staff', json_encode(AuditLog::query()->get()));
    }

    public function test_submitting_again_edits_the_same_review_in_place(): void
    {
        $shop = Shop::factory()->create();
        $author = User::factory()->create();
        $first = $this->service()->submitShopReview($author, $shop, $this->goodReview);

        $second = $this->service()->submitShopReview($author, $shop, ['rating' => 2, 'body' => 'It got worse over time.']);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, Review::query()->count());
        $this->assertSame(2, $second->fresh()->rating);
        $this->assertSame('It got worse over time.', $second->fresh()->body);
        $this->assertSame(1, AuditLog::query()->where('action', 'review.updated')->count());
    }

    public function test_editing_stops_after_the_window_but_withdrawing_and_rewriting_still_works(): void
    {
        $shop = Shop::factory()->create();
        $author = User::factory()->create();
        $review = $this->service()->submitShopReview($author, $shop, $this->goodReview);
        $review->forceFill(['created_at' => now()->subDays(31)])->saveQuietly();

        $this->assertRuleViolated('review.edit_window_closed', fn () => $this->service()->submitShopReview($author, $shop, $this->goodReview));

        $this->service()->withdrawReview($author, $review->fresh());
        $this->assertSame(ReviewStatus::Withdrawn, $review->fresh()->status);

        $again = $this->service()->submitShopReview($author, $shop, ['rating' => 5, 'body' => 'Back again, much better now.']);
        $this->assertSame($review->getKey(), $again->getKey());
        $this->assertSame(ReviewStatus::Published, $again->fresh()->status);
    }

    public function test_a_hidden_review_cannot_be_changed_or_withdrawn_by_its_author(): void
    {
        $shop = Shop::factory()->create();
        $author = User::factory()->create();
        $review = Review::factory()->for($shop)->for($author, 'author')->hidden()->create();

        $this->assertRuleViolated('review.hidden_by_moderation', fn () => $this->service()->submitShopReview($author, $shop, $this->goodReview));
        $this->assertRuleViolated('review.hidden_by_moderation', fn () => $this->service()->withdrawReview($author, $review));
        $this->assertSame(ReviewStatus::Hidden, $review->fresh()->status);
    }

    public function test_only_the_author_may_withdraw_a_review(): void
    {
        $review = Review::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service()->withdrawReview(User::factory()->create(), $review);
    }

    public function test_a_withdrawn_review_cannot_be_withdrawn_twice(): void
    {
        $review = Review::factory()->withdrawn()->create();

        $this->assertRuleViolated('review.already_withdrawn', fn () => $this->service()->withdrawReview($review->author, $review));
    }

    public function test_content_is_validated(): void
    {
        $shop = Shop::factory()->create();
        $author = User::factory()->create();

        foreach ([0, 6, 'x', 3.5, null] as $rating) {
            $this->assertRuleViolated('review.invalid_rating', fn () => $this->service()->submitShopReview($author, $shop, [...$this->goodReview, 'rating' => $rating]));
        }

        $this->assertRuleViolated('review.invalid_body', fn () => $this->service()->submitShopReview($author, $shop, [...$this->goodReview, 'body' => 'short']));
        $this->assertRuleViolated('review.invalid_body', fn () => $this->service()->submitShopReview($author, $shop, [...$this->goodReview, 'body' => str_repeat('a', 2001)]));
        $this->assertRuleViolated('review.invalid_title', fn () => $this->service()->submitShopReview($author, $shop, [...$this->goodReview, 'title' => str_repeat('t', 161)]));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_only_active_shops_and_active_listings_can_be_reviewed(): void
    {
        $author = User::factory()->create();
        $draft = Shop::factory()->draft()->create();
        $archivedListing = ShopProduct::factory()->archived()->create();

        $this->assertRuleViolated('review.shop_not_reviewable', fn () => $this->service()->submitShopReview($author, $draft, $this->goodReview));
        $this->assertRuleViolated('review.listing_not_reviewable', fn () => $this->service()->submitProductReview($author, $archivedListing, $this->goodReview));
    }

    public function test_staff_cannot_review_their_own_shop_or_its_products(): void
    {
        $listing = ShopProduct::factory()->create();
        $staff = $this->staffOf($listing->shop);

        $this->assertRuleViolated('review.own_shop', fn () => $this->service()->submitShopReview($staff, $listing->shop, $this->goodReview));
        $this->assertRuleViolated('review.own_shop', fn () => $this->service()->submitProductReview($staff, $listing, $this->goodReview));
    }

    public function test_a_product_review_is_unverified_unless_a_valid_purchase_code_is_presented(): void
    {
        $listing = ShopProduct::factory()->create();
        $author = User::factory()->create();

        $review = $this->service()->submitProductReview($author, $listing, $this->goodReview);

        $this->assertSame($listing->getKey(), $review->shop_product_id);
        $this->assertFalse($review->isVerified());
        $this->assertSame(0, ReviewVerification::query()->count());
    }

    public function test_a_valid_purchase_code_verifies_the_review_and_is_used_up(): void
    {
        $listing = ShopProduct::factory()->create();
        $author = User::factory()->create();
        [$code] = $this->purchaseCodeFor($author, $listing);

        $review = $this->service()->submitProductReview($author, $listing, $this->goodReview, $code);

        $this->assertTrue($review->isVerified());
        $this->assertSame(1, TokenRedemption::query()->count());
        $this->assertSame($review->getKey(), ReviewVerification::query()->sole()->review_id);

        $this->assertRuleViolated('review.already_verified', fn () => $this->service()->submitProductReview($author, $listing, $this->goodReview, $code));

        $otherListing = ShopProduct::factory()->for($listing->shop)->create();
        $this->assertRuleViolated('verification.wrong_scope', fn () => $this->service()->submitProductReview($author, $otherListing, $this->goodReview, $code));
        $this->assertSame(1, Review::query()->count());
        $this->assertSame(1, TokenRedemption::query()->count());
    }

    public function test_a_refused_code_saves_nothing(): void
    {
        $listing = ShopProduct::factory()->create();
        $author = User::factory()->create();
        [$code] = $this->purchaseCodeFor(User::factory()->create(), $listing);

        $this->assertRuleViolated('verification.unknown_credential', fn () => $this->service()->submitProductReview($author, $listing, $this->goodReview, $code));
        $this->assertRuleViolated('verification.unknown_credential', fn () => $this->service()->submitProductReview($author, $listing, $this->goodReview, 'ismv_notarealcodeatallnotarealcodeatallnotarea'));

        $this->assertSame(0, Review::query()->count());
        $this->assertSame(0, TokenRedemption::query()->count());
    }

    public function test_a_code_for_another_listing_does_not_verify_this_one(): void
    {
        $listing = ShopProduct::factory()->create();
        $other = ShopProduct::factory()->for($listing->shop)->create();
        $author = User::factory()->create();
        [$code] = $this->purchaseCodeFor($author, $other);

        $this->assertRuleViolated('verification.wrong_scope', fn () => $this->service()->submitProductReview($author, $listing, $this->goodReview, $code));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_clients_cannot_forge_the_verified_badge(): void
    {
        $listing = ShopProduct::factory()->create();
        $author = User::factory()->create();

        $review = $this->service()->submitProductReview($author, $listing, [...$this->goodReview, 'verified' => true, 'is_verified' => true, 'status' => 'pending', 'verification' => ['token' => 'x']]);

        $this->assertFalse($review->isVerified());
        $this->assertSame(ReviewStatus::Published, $review->status);
        $this->assertSame(0, ReviewVerification::query()->count());
    }

    public function test_an_existing_review_can_be_verified_later_and_only_once(): void
    {
        $listing = ShopProduct::factory()->create();
        $author = User::factory()->create();
        $review = $this->service()->submitProductReview($author, $listing, $this->goodReview);
        [$code] = $this->purchaseCodeFor($author, $listing);

        $this->assertTrue($this->service()->verifyOwnReview($author, $review, $code)->isVerified());

        [$secondCode] = $this->purchaseCodeFor($author, ShopProduct::factory()->for($listing->shop)->create());
        $this->assertRuleViolated('review.already_verified', fn () => $this->service()->verifyOwnReview($author, $review, $secondCode));
    }

    public function test_only_the_author_may_verify_a_review_and_shop_reviews_cannot_be_verified(): void
    {
        $listing = ShopProduct::factory()->create();
        $author = User::factory()->create();
        $review = $this->service()->submitProductReview($author, $listing, $this->goodReview);
        $shopReview = $this->service()->submitShopReview($author, $listing->shop, $this->goodReview);

        $this->assertRuleViolated('review.verification_needs_product', fn () => $this->service()->verifyOwnReview($author, $shopReview, 'ismv_x'));

        $this->expectException(AuthorizationException::class);
        $this->service()->verifyOwnReview(User::factory()->create(), $review, 'ismv_x');
    }
}
