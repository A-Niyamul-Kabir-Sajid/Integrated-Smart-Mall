<?php

namespace Tests\Feature\Modules\ReviewsModeration;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Enums\ModerationActionType;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\ModerationAction;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReply;
use App\Modules\ReviewsModeration\Models\ReviewReport;
use App\Modules\ReviewsModeration\Models\ReviewVerification;
use App\Modules\ReviewsModeration\Services\ReviewPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ReviewDataLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_has_one_shop_review_per_shop_but_may_review_other_shops(): void
    {
        $author = User::factory()->create();
        $shop = Shop::factory()->create();
        Review::factory()->for($author, 'author')->for($shop)->create();

        Review::factory()->for($author, 'author')->for(Shop::factory())->create();
        Review::factory()->for(User::factory(), 'author')->for($shop)->create();

        $this->expectException(QueryException::class);
        Review::factory()->for($author, 'author')->for($shop)->create();
    }

    public function test_a_user_has_one_review_per_listing_and_a_shop_review_does_not_use_it_up(): void
    {
        $author = User::factory()->create();
        $listing = ShopProduct::factory()->create();
        $otherListing = ShopProduct::factory()->for($listing->shop)->create();

        Review::factory()->for($author, 'author')->for($listing->shop)->create();
        Review::factory()->for($author, 'author')->forListing($listing)->create();
        Review::factory()->for($author, 'author')->forListing($otherListing)->create();

        $this->assertSame(3, Review::query()->where('author_user_id', $author->getKey())->count());

        $this->expectException(QueryException::class);
        Review::factory()->for($author, 'author')->forListing($listing)->create();
    }

    public function test_a_listing_review_must_name_the_shop_that_sells_the_listing(): void
    {
        $listing = ShopProduct::factory()->create();

        $this->expectException(QueryException::class);
        Review::factory()->create(['shop_id' => Shop::factory()->create()->getKey(), 'shop_product_id' => $listing->getKey()]);
    }

    public function test_a_reporter_reports_a_review_once(): void
    {
        $review = Review::factory()->create();
        $reporter = User::factory()->create();
        ReviewReport::factory()->for($review)->create(['reporter_user_id' => $reporter->getKey()]);

        $this->expectException(QueryException::class);
        ReviewReport::factory()->for($review)->create(['reporter_user_id' => $reporter->getKey()]);
    }

    public function test_a_redeemed_credential_verifies_only_one_review(): void
    {
        $listing = ShopProduct::factory()->create();
        $first = Review::factory()->forListing($listing)->verified()->create();
        $tokenId = $first->verification->verification_token_id;
        $second = Review::factory()->forListing($listing)->create();

        $this->expectException(QueryException::class);
        ReviewVerification::query()->create(['review_id' => $second->getKey(), 'verification_token_id' => $tokenId, 'verified_at' => now()]);
    }

    public function test_the_verified_badge_comes_from_the_binding_and_never_from_the_review_row(): void
    {
        $listing = ShopProduct::factory()->create();
        $plain = Review::factory()->forListing($listing)->create();
        $verified = Review::factory()->forListing($listing)->verified()->create();

        $this->assertFalse($plain->isVerified());
        $this->assertTrue($verified->isVerified());
        $this->assertArrayNotHasKey('verified', $verified->getAttributes());

        $verified->verification->update(['revoked_at' => now(), 'revocation_reason' => 'Sale refunded']);

        $this->assertFalse($verified->fresh()->isVerified());
        $this->assertSame(2, Review::query()->count(), 'The review itself stays; only the badge goes.');
    }

    public function test_author_and_target_of_a_review_never_change(): void
    {
        $listing = ShopProduct::factory()->create();
        $review = Review::factory()->forListing($listing)->create();

        foreach ([
            fn () => $review->update(['author_user_id' => User::factory()->create()->getKey()]),
            fn () => $review->update(['shop_id' => Shop::factory()->create()->getKey()]),
            fn () => $review->update(['shop_product_id' => null]),
            fn () => $review->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the change to be refused.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }

        $review->fresh()->update(['rating' => 2, 'body' => 'Changed my mind about it.']);
        $this->assertSame(2, $review->fresh()->rating);
    }

    public function test_review_status_follows_its_lifecycle(): void
    {
        $review = Review::factory()->create();

        $review->update(['status' => ReviewStatus::Hidden]);
        $review->update(['status' => ReviewStatus::Published]);
        $review->update(['status' => ReviewStatus::Withdrawn]);
        $review->update(['status' => ReviewStatus::Published]);

        $this->assertSame(ReviewStatus::Published, $review->fresh()->status);

        $hidden = Review::factory()->hidden()->create();
        $this->expectException(LogicException::class);
        $hidden->update(['status' => ReviewStatus::Withdrawn]);
    }

    public function test_only_published_reviews_are_public(): void
    {
        $this->assertTrue(ReviewStatus::Published->isPublic());
        $this->assertFalse(ReviewStatus::Pending->isPublic());
        $this->assertFalse(ReviewStatus::Hidden->isPublic());
        $this->assertFalse(ReviewStatus::Withdrawn->isPublic());
    }

    public function test_moderation_history_replies_reports_and_verification_are_not_erased(): void
    {
        $review = Review::factory()->create();
        $action = ModerationAction::factory()->for($review)->create(['action' => ModerationActionType::Hide]);
        $reply = ReviewReply::factory()->for($review)->create();
        $report = ReviewReport::factory()->for($review)->create();

        foreach ([
            fn () => $action->update(['reason' => 'Edited']),
            fn () => $action->delete(),
            fn () => $reply->delete(),
            fn () => $reply->update(['review_id' => Review::factory()->create()->getKey()]),
            fn () => $report->delete(),
            fn () => $report->update(['reason_code' => 'abuse']),
            fn () => $report->update(['reporter_user_id' => User::factory()->create()->getKey()]),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the change to be refused.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_the_reporter_is_hidden_from_serialisation(): void
    {
        $report = ReviewReport::factory()->create();

        $this->assertArrayNotHasKey('reporter_user_id', $report->toArray());
    }

    public function test_edit_window_rules(): void
    {
        $fresh = Review::factory()->create();
        $old = Review::factory()->create(['created_at' => now()->subDays(31)]);
        $oldWithdrawn = Review::factory()->withdrawn()->create(['created_at' => now()->subDays(90)]);
        $hidden = Review::factory()->hidden()->create();

        $this->assertTrue(ReviewPolicy::authorMayEdit($fresh));
        $this->assertFalse(ReviewPolicy::authorMayEdit($old));
        $this->assertTrue(ReviewPolicy::authorMayEdit($oldWithdrawn));
        $this->assertFalse(ReviewPolicy::authorMayEdit($hidden));
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, ReviewPolicy::editDeadline($fresh)->timestamp, 5);
    }
}
