<?php

namespace Tests\Feature\Modules\ReviewsModeration;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Services\PurchaseService;
use App\Modules\ReviewsModeration\Enums\ReplyStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReply;
use App\Modules\ReviewsModeration\Services\ReviewQueryService;
use App\Modules\ReviewsModeration\Services\ReviewReplyService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewReplyAndQueryTest extends TestCase
{
    use CreatesReviewFixtures, RefreshDatabase;

    private function replies(): ReviewReplyService
    {
        return app(ReviewReplyService::class);
    }

    private function queries(): ReviewQueryService
    {
        return app(ReviewQueryService::class);
    }

    public function test_staff_of_the_reviewed_shop_reply_once_and_can_edit_and_hide_it(): void
    {
        $review = Review::factory()->create();
        $staff = $this->staffOf($review->shop);

        $reply = $this->replies()->reply($staff, $review, '  Thank you for visiting!  ');

        $this->assertSame('Thank you for visiting!', $reply->body);
        $this->assertSame($staff->getKey(), $reply->author_user_id);
        $this->assertRuleViolated('reply.already_exists', fn () => $this->replies()->reply($staff, $review, 'Again'));

        $this->replies()->editReply($staff, $reply, 'Thanks, come again.');
        $this->assertSame('Thanks, come again.', $reply->fresh()->body);

        $this->replies()->hideReply($staff, $reply);
        $this->assertSame(ReplyStatus::Hidden, $reply->fresh()->status);
        $this->assertRuleViolated('reply.not_public', fn () => $this->replies()->editReply($staff, $reply->fresh(), 'Edit hidden'));

        $this->replies()->reply($staff, $review, 'A fresh answer.');
        $this->assertSame(2, ReviewReply::query()->count());
        $this->assertEqualsCanonicalizing(['review.reply_posted', 'review.reply_edited', 'review.reply_hidden', 'review.reply_posted'], AuditLog::query()->where('action', 'like', 'review.%')->pluck('action')->all());
    }

    public function test_replies_are_scoped_to_the_reviewed_shop(): void
    {
        $review = Review::factory()->create();
        $reply = ReviewReply::factory()->for($review)->create();
        $outsiders = [User::factory()->create(), $this->staffOf(Shop::factory()->create()), $review->author];

        foreach ($outsiders as $user) {
            foreach ([
                fn () => $this->replies()->reply($user, $review, 'Nope'),
                fn () => $this->replies()->editReply($user, $reply, 'Nope'),
                fn () => $this->replies()->hideReply($user, $reply),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail('Expected the reply action to be refused.');
                } catch (AuthorizationException) {
                    $this->assertNotSame('Nope', $reply->fresh()->body);
                }
            }
        }

        $this->assertSame(1, ReviewReply::query()->count());
    }

    public function test_replies_need_a_public_review_and_a_valid_body(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $hidden = Review::factory()->for($shop)->hidden()->create();
        $review = Review::factory()->for($shop)->create();

        $this->assertRuleViolated('reply.review_not_public', fn () => $this->replies()->reply($staff, $hidden, 'Hello there'));
        $this->assertRuleViolated('reply.invalid_body', fn () => $this->replies()->reply($staff, $review, '   '));
        $this->assertRuleViolated('reply.invalid_body', fn () => $this->replies()->reply($staff, $review, str_repeat('a', 1001)));
    }

    public function test_a_reply_never_changes_the_customers_review(): void
    {
        $review = Review::factory()->create(['body' => 'My original words about this shop.']);
        $before = $review->fresh()->only(['rating', 'title', 'body', 'status', 'updated_at']);

        $this->replies()->reply($this->staffOf($review->shop), $review, 'We are sorry to hear that.');

        $this->assertEquals($before, $review->fresh()->only(['rating', 'title', 'body', 'status', 'updated_at']));
    }

    public function test_the_public_shop_list_shows_only_published_reviews_of_the_shop_with_published_replies(): void
    {
        $listing = ShopProduct::factory()->create();
        $shop = $listing->shop;
        $shown = Review::factory()->for($shop)->create();
        ReviewReply::factory()->for($shown)->create(['body' => 'Visible reply']);
        ReviewReply::factory()->for($shown)->hidden()->create(['body' => 'Hidden reply']);
        Review::factory()->for($shop)->hidden()->create();
        Review::factory()->for($shop)->withdrawn()->create();
        Review::factory()->for($shop)->pending()->create();
        Review::factory()->forListing($listing)->create();
        Review::factory()->for(Shop::factory())->create();

        $page = $this->queries()->publicForShop($shop);

        $this->assertSame([$shown->getKey()], $page->pluck('review_id')->all());
        $this->assertSame(['Visible reply'], $page->first()->replies->pluck('body')->all());
        $this->assertSame($shown->author->display_name, $page->first()->author->display_name);
        $this->assertArrayNotHasKey('email', $page->first()->author->toArray());
    }

    public function test_the_listing_list_and_the_verified_filter(): void
    {
        $listing = ShopProduct::factory()->create();
        $plain = Review::factory()->forListing($listing)->create();
        $verified = Review::factory()->forListing($listing)->verified()->create();
        Review::factory()->for($listing->shop)->create();

        $all = $this->queries()->publicForListing($listing);
        $onlyVerified = $this->queries()->publicForListing($listing, verifiedOnly: true);

        $this->assertEqualsCanonicalizing([$plain->getKey(), $verified->getKey()], $all->pluck('review_id')->all());
        $this->assertSame([$verified->getKey()], $onlyVerified->pluck('review_id')->all());
    }

    public function test_a_customer_sees_their_own_reviews_in_any_status(): void
    {
        $author = User::factory()->create();
        $published = Review::factory()->for($author, 'author')->create();
        $hidden = Review::factory()->for($author, 'author')->hidden()->create();
        Review::factory()->create();

        $this->assertEqualsCanonicalizing([$published->getKey(), $hidden->getKey()], $this->queries()->forAuthor($author)->pluck('review_id')->all());
        $this->assertSame($hidden->getKey(), $this->queries()->ownReview($author, $hidden->shop)?->getKey());
        $this->assertNull($this->queries()->ownReview(User::factory()->create(), $hidden->shop));
    }

    public function test_the_shop_inbox_is_for_staff_of_that_shop_and_can_filter_unanswered(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $answered = Review::factory()->for($shop)->create();
        ReviewReply::factory()->for($answered)->create();
        $open = Review::factory()->for($shop)->create();
        Review::factory()->for($shop)->hidden()->create();
        Review::factory()->create();

        $this->assertCount(2, $this->queries()->inboxForShop($staff, $shop));
        $this->assertSame([$open->getKey()], $this->queries()->inboxForShop($staff, $shop, unansweredOnly: true)->pluck('review_id')->all());

        $this->expectException(AuthorizationException::class);
        $this->queries()->inboxForShop($this->staffOf(Shop::factory()->create()), $shop);
    }

    public function test_a_voided_or_refunded_sale_revokes_the_verified_badge_but_keeps_the_review(): void
    {
        $listing = ShopProduct::factory()->create();
        $voided = Review::factory()->forListing($listing)->verified()->create();
        $refunded = Review::factory()->forListing($listing)->verified()->create();
        $untouched = Review::factory()->forListing($listing)->verified()->create();
        $staff = $this->staffOf($listing->shop);
        $purchaseOf = fn (Review $review) => $review->verification->redemption->token->purchaseItem->purchase;

        app(PurchaseService::class)->changeStatus($staff, $purchaseOf($voided), PurchaseStatus::Voided);
        app(PurchaseService::class)->changeStatus($staff, $purchaseOf($refunded), PurchaseStatus::Refunded);

        $this->assertFalse($voided->fresh()->isVerified());
        $this->assertFalse($refunded->fresh()->isVerified());
        $this->assertTrue($untouched->fresh()->isVerified());
        $this->assertSame('Purchase voided', $voided->fresh()->verification->revocation_reason);
        $this->assertTrue($voided->fresh()->status->isPublic(), 'The review stays as an ordinary opinion.');
        $this->assertSame(2, AuditLog::query()->where('action', 'review.verification_revoked')->count());
    }
}
