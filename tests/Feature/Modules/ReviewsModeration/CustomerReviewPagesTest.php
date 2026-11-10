<?php

namespace Tests\Feature\Modules\ReviewsModeration;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReply;
use App\Modules\ReviewsModeration\Models\ReviewVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReviewPagesTest extends TestCase
{
    use CreatesReviewFixtures, RefreshDatabase;

    private Mall $mall;

    private Shop $shop;

    private ShopProduct $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['slug' => 'demo-city-mall']);
        $this->shop = Shop::factory()->for($this->mall)->create(['name' => 'Fresh Mart', 'slug' => 'fresh-mart']);
        $this->listing = ShopProduct::factory()->for($this->shop)->create();
    }

    private function shopUrl(): string
    {
        return route('shops.reviews.index', ['publicMall' => $this->mall->slug, 'publicShop' => $this->shop->slug]);
    }

    private function listingUrl(array $query = []): string
    {
        return route('listings.reviews.index', ['publicMall' => $this->mall->slug, 'publicListing' => $this->listing->getKey(), ...$query]);
    }

    public function test_anyone_can_read_published_reviews_with_the_summary_and_shop_replies(): void
    {
        $review = Review::factory()->for($this->shop)->rated(5)->create(['title' => 'Lovely place', 'body' => 'Fresh produce every morning.']);
        ReviewReply::factory()->for($review)->create(['body' => 'Thank you for shopping with us!']);
        Review::factory()->for($this->shop)->hidden()->create(['body' => 'This hidden text must never show.']);

        $this->get($this->shopUrl())
            ->assertOk()
            ->assertSee('Lovely place')
            ->assertSee('Fresh produce every morning.')
            ->assertSee('Reply from Fresh Mart')
            ->assertSee('Thank you for shopping with us!')
            ->assertSee('5.0')
            ->assertDontSee('This hidden text must never show.')
            ->assertSee('Sign in');
    }

    public function test_a_review_page_for_an_unknown_or_unlisted_target_is_not_found(): void
    {
        $draft = Shop::factory()->for($this->mall)->draft()->create(['slug' => 'secret']);
        $foreign = ShopProduct::factory()->create();

        $this->get(route('shops.reviews.index', ['publicMall' => $this->mall->slug, 'publicShop' => $draft->slug]))->assertNotFound();
        $this->get(route('listings.reviews.index', ['publicMall' => $this->mall->slug, 'publicListing' => $foreign->getKey()]))->assertNotFound();
        $this->get(route('listings.reviews.index', ['publicMall' => $this->mall->slug, 'publicListing' => 'not-a-uuid']))->assertNotFound();
    }

    public function test_the_listing_page_shows_the_verified_badge_and_filters_on_it(): void
    {
        Review::factory()->forListing($this->listing)->create(['body' => 'Plain opinion about the product.']);
        Review::factory()->forListing($this->listing)->verified()->create(['body' => 'Bought it last week, works well.']);

        $this->get($this->listingUrl())->assertOk()->assertSee('Verified purchase')->assertSee('Plain opinion about the product.');
        $this->get($this->listingUrl(['verified' => 1]))->assertOk()->assertSee('Bought it last week, works well.')->assertDontSee('Plain opinion about the product.');
    }

    public function test_guests_cannot_write_reviews(): void
    {
        $this->post(route('shops.reviews.store', ['publicMall' => $this->mall->slug, 'publicShop' => $this->shop->slug]), $this->goodReview)->assertRedirect(route('login'));
        $this->post(route('reviews.withdraw', Review::factory()->create()))->assertRedirect(route('login'));
        $this->get(route('account.reviews'))->assertRedirect(route('login'));
        $this->assertSame(0, Review::query()->where('author_user_id', '!=', null)->where('body', $this->goodReview['body'])->count());
    }

    public function test_a_signed_in_customer_publishes_a_shop_review_and_then_edits_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($this->shopUrl())->assertOk()->assertSee('Write a review');
        $this->actingAs($user)->post($this->shopUrl(), $this->goodReview)->assertRedirect($this->shopUrl())->assertSessionHas('status');

        $this->assertSame(1, Review::query()->count());
        $this->actingAs($user)->get($this->shopUrl())->assertSee('Your review')->assertSee('Update review')->assertSee('Friendly staff and fair prices.');

        $this->actingAs($user)->post($this->shopUrl(), ['rating' => 2, 'body' => 'Changed my mind completely.'])->assertRedirect($this->shopUrl());
        $this->assertSame(1, Review::query()->count());
        $this->assertSame(2, Review::query()->sole()->rating);
    }

    public function test_the_form_validates_and_the_service_rules_show_as_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post($this->shopUrl(), ['rating' => 9, 'body' => 'short'])->assertSessionHasErrors(['rating', 'body']);
        $this->assertSame(0, Review::query()->count());

        $staff = $this->staffOf($this->shop);
        $this->actingAs($staff)->from($this->shopUrl())->post($this->shopUrl(), $this->goodReview)
            ->assertRedirect($this->shopUrl())
            ->assertSessionHasErrors('business');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_staff_see_why_they_cannot_review_their_own_shop(): void
    {
        $this->actingAs($this->staffOf($this->shop))->get($this->shopUrl())->assertOk()->assertSee('You work at this shop');
    }

    public function test_a_product_review_with_a_valid_purchase_code_is_verified_but_a_forged_flag_is_not(): void
    {
        $user = User::factory()->create();
        $forger = User::factory()->create();
        [$code] = $this->purchaseCodeFor($user, $this->listing);

        $this->actingAs($forger)->post($this->listingUrl(), [...$this->goodReview, 'verified' => 1, 'is_verified' => true, 'purchase_code' => ''])->assertRedirect($this->listingUrl());
        $this->assertFalse(Review::query()->where('author_user_id', $forger->getKey())->sole()->isVerified());

        $this->actingAs($user)->post($this->listingUrl(), [...$this->goodReview, 'purchase_code' => $code])->assertRedirect($this->listingUrl());
        $this->assertTrue(Review::query()->where('author_user_id', $user->getKey())->sole()->isVerified());
        $this->get($this->listingUrl())->assertSee('Verified purchase');
    }

    public function test_a_wrong_purchase_code_publishes_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from($this->listingUrl())->post($this->listingUrl(), [...$this->goodReview, 'purchase_code' => 'ismv_thisisnotavalidcodethisisnotavalidcode1'])
            ->assertRedirect($this->listingUrl())
            ->assertSessionHasErrors('business');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_customer_verifies_an_existing_review_later(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->forListing($this->listing)->for($user, 'author')->create();
        [$code] = $this->purchaseCodeFor($user, $this->listing);

        $this->actingAs($user)->get($this->listingUrl())->assertSee('Verify your purchase');
        $this->actingAs($user)->post(route('reviews.verify', $review), ['purchase_code' => $code])->assertRedirect()->assertSessionHas('status');

        $this->assertTrue($review->fresh()->isVerified());
        $this->assertSame(1, ReviewVerification::query()->count());
    }

    public function test_a_customer_sees_and_withdraws_only_their_own_reviews(): void
    {
        $user = User::factory()->create();
        $mine = Review::factory()->for($this->shop)->for($user, 'author')->create(['body' => 'My own opinion of the shop.']);
        $other = Review::factory()->for($this->shop)->create(['body' => 'Someone else entirely wrote this.']);

        $this->actingAs($user)->get(route('account.reviews'))->assertOk()->assertSee('My own opinion of the shop.')->assertDontSee('Someone else entirely wrote this.');

        $this->actingAs($user)->post(route('reviews.withdraw', $other))->assertForbidden();
        $this->assertSame(ReviewStatus::Published, $other->fresh()->status);

        $this->actingAs($user)->post(route('reviews.withdraw', $mine))->assertRedirect(route('account.reviews'));
        $this->assertSame(ReviewStatus::Withdrawn, $mine->fresh()->status);
        $this->app['auth']->forgetGuards();
        $this->get($this->shopUrl())->assertDontSee('My own opinion of the shop.');
    }

    public function test_a_hidden_review_shows_to_its_author_with_an_explanation_and_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        Review::factory()->for($this->shop)->for($user, 'author')->hidden()->create();

        $this->actingAs($user)->get($this->shopUrl())->assertSee('A moderator hid your review');
        $this->actingAs($user)->from($this->shopUrl())->post($this->shopUrl(), $this->goodReview)->assertSessionHasErrors('business');
    }

    public function test_the_shop_page_shows_the_rating_summary_and_the_product_page_shows_listing_ratings(): void
    {
        Review::factory()->for($this->shop)->rated(4)->create();
        Review::factory()->forListing($this->listing)->rated(2)->create();
        $variant = $this->listing->variant;

        $this->get(route('shops.show', ['publicMall' => $this->mall->slug, 'publicShop' => $this->shop->slug]))
            ->assertOk()->assertSee('Read all reviews')->assertSee('4.0');

        $this->get(route('products.show', ['publicMall' => $this->mall->slug, 'publicProduct' => $variant->product_id]))
            ->assertOk()->assertSee(route('listings.reviews.index', ['publicMall' => $this->mall->slug, 'publicListing' => $this->listing->getKey()]), false);
    }

    public function test_submissions_are_rate_limited_per_account(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 10) as $_) {
            $this->actingAs($user)->post($this->shopUrl(), $this->goodReview)->assertRedirect();
        }

        $this->actingAs($user)->post($this->shopUrl(), $this->goodReview)->assertStatus(429);
    }
}
