<?php

namespace Tests\Feature\Modules\ReviewsModeration;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\RoleAssignmentService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Enums\ReportReason;
use App\Modules\ReviewsModeration\Enums\ReportStatus;
use App\Modules\ReviewsModeration\Enums\ReviewStatus;
use App\Modules\ReviewsModeration\Models\ModerationAction;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationPagesTest extends TestCase
{
    use CreatesReviewFixtures, RefreshDatabase;

    private Mall $mall;

    private Shop $shop;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create(['slug' => 'demo-city-mall']);
        $this->shop = Shop::factory()->for($this->mall)->create(['slug' => 'fresh-mart']);
        $this->moderator = $this->moderatorOf($this->shop);
    }

    private function desk(array $query = []): string
    {
        return route('moderation.index', [$this->mall, ...$query]);
    }

    public function test_customers_report_a_review_from_its_page_and_guests_cannot(): void
    {
        $review = Review::factory()->for($this->shop)->create(['body' => 'A review that someone dislikes.']);
        $reporter = User::factory()->create(['display_name' => 'Rita Reporter']);
        $page = route('shops.reviews.index', ['publicMall' => $this->mall->slug, 'publicShop' => $this->shop->slug]);

        $this->post(route('reviews.report', $review), ['reason_code' => 'spam'])->assertRedirect(route('login'));
        $this->actingAs($reporter)->get($page)->assertSee('Report this review');

        $this->actingAs($reporter)->post(route('reviews.report', $review), ['reason_code' => 'abuse', 'details' => 'Insults the staff.'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame(1, ReviewReport::query()->count());

        $this->actingAs($reporter)->from($page)->post(route('reviews.report', $review), ['reason_code' => 'spam'])->assertSessionHasErrors('business');
        $this->actingAs($reporter)->post(route('reviews.report', $review), ['reason_code' => 'nonsense'])->assertSessionHasErrors('reason_code');
        $this->assertSame(1, ReviewReport::query()->count());
    }

    public function test_the_author_does_not_see_a_report_option_on_their_own_review(): void
    {
        $review = Review::factory()->for($this->shop)->create();
        $page = route('shops.reviews.index', ['publicMall' => $this->mall->slug, 'publicShop' => $this->shop->slug]);

        $this->actingAs($review->author)->get($page)->assertDontSee('Report this review');
        $this->get($page)->assertDontSee('Report this review');
    }

    public function test_the_moderation_desk_is_only_for_administrators_of_that_mall(): void
    {
        $this->get($this->desk())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($this->desk())->assertForbidden();
        $this->actingAs($this->staffOf($this->shop))->get($this->desk())->assertForbidden();
        $this->actingAs($this->moderatorOf(Shop::factory()->create()))->get($this->desk())->assertForbidden();

        $this->actingAs($this->moderator)->get($this->desk())->assertOk()->assertSee('No open reports');

        $platformAdmin = User::factory()->create();
        app(RoleAssignmentService::class)->assignPlatformRole($platformAdmin, RoleCode::PlatformAdmin);
        $this->actingAs($platformAdmin)->get($this->desk())->assertOk();
    }

    public function test_the_queue_shows_what_was_reported_and_why_but_never_who_reported(): void
    {
        $review = Review::factory()->for($this->shop)->create(['body' => 'The reported review text.']);
        $reporter = User::factory()->create(['display_name' => 'Rita Reporter', 'email' => 'rita.reporter@example.test']);
        ReviewReport::factory()->for($review)->create(['reporter_user_id' => $reporter->getKey(), 'reason_code' => ReportReason::Abuse, 'details' => 'Insults the staff.']);

        $html = $this->actingAs($this->moderator)->get($this->desk())->assertOk()
            ->assertSee('The reported review text.')->assertSee('1 open report')->assertSee('Abusive or offensive')->assertSee('Insults the staff.')
            ->getContent();

        $this->assertStringNotContainsString('Rita Reporter', $html);
        $this->assertStringNotContainsString('rita.reporter@example.test', $html);
        $this->assertStringNotContainsString($reporter->getKey(), $html);

        $authorHtml = $this->actingAs($review->author)->get(route('account.reviews'))->getContent();
        $this->assertStringNotContainsString('Rita Reporter', $authorHtml);
        $this->assertStringNotContainsString('Insults the staff.', $authorHtml);
    }

    public function test_a_moderator_hides_a_review_through_the_desk_with_a_reason(): void
    {
        $review = Review::factory()->for($this->shop)->create(['body' => 'Offensive words in this review.']);
        $report = ReviewReport::factory()->for($review)->create();
        $page = route('shops.reviews.index', ['publicMall' => $this->mall->slug, 'publicShop' => $this->shop->slug]);

        $this->actingAs($this->moderator)->from($this->desk())->post(route('moderation.hide', [$this->mall, $review]), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(ReviewStatus::Published, $review->fresh()->status);

        $this->actingAs($this->moderator)->post(route('moderation.hide', [$this->mall, $review]), ['reason' => 'Violates the review policy.'])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(ReviewStatus::Hidden, $review->fresh()->status);
        $this->assertSame(ReportStatus::Resolved, $report->fresh()->status);
        $this->assertSame('Violates the review policy.', ModerationAction::query()->sole()->reason);
        $this->app['auth']->forgetGuards();
        $this->get($page)->assertDontSee('Offensive words in this review.');

        $this->actingAs($this->moderator)->get($this->desk(['view' => 'hidden']))->assertOk()->assertSee('Offensive words in this review.')->assertSee('Restore review')->assertSee('Moderation history');
        $this->actingAs($this->moderator)->post(route('moderation.restore', [$this->mall, $review]), ['reason' => 'Appealed and checked.'])->assertRedirect();
        $this->assertSame(ReviewStatus::Published, $review->fresh()->status);
    }

    public function test_dismissing_and_removing_the_verified_badge_through_the_desk(): void
    {
        $listing = ShopProduct::factory()->for($this->shop)->create();
        $review = Review::factory()->forListing($listing)->verified()->create();
        ReviewReport::factory()->for($review)->create();

        $this->actingAs($this->moderator)->get($this->desk())->assertSee('Remove verified badge');
        $this->actingAs($this->moderator)->post(route('moderation.dismiss', [$this->mall, $review]), ['reason' => 'Looks genuine.'])->assertRedirect();
        $this->assertSame(0, ReviewReport::query()->open()->count());
        $this->assertSame(ReviewStatus::Published, $review->fresh()->status);

        $this->actingAs($this->moderator)->post(route('moderation.revoke-verification', [$this->mall, $review]), ['reason' => 'Receipt could not be confirmed.'])->assertRedirect();
        $this->assertFalse($review->fresh()->isVerified());
    }

    public function test_a_moderator_cannot_reach_another_malls_reviews_through_their_own_mall_url(): void
    {
        $foreign = Review::factory()->create();
        $foreignReport = ReviewReport::factory()->for($foreign)->create();

        $this->actingAs($this->moderator)->post(route('moderation.hide', [$this->mall, $foreign]), ['reason' => 'Not mine'])->assertNotFound();
        $this->actingAs($this->moderator)->post(route('moderation.reports.dismiss', [$this->mall, $foreignReport]), ['reason' => 'Not mine'])->assertNotFound();
        $this->assertSame(ReviewStatus::Published, $foreign->fresh()->status);
        $this->assertSame(ReportStatus::Open, $foreignReport->fresh()->status);
    }

    public function test_non_moderators_cannot_post_moderation_actions(): void
    {
        $review = Review::factory()->for($this->shop)->create();

        $this->post(route('moderation.hide', [$this->mall, $review]), ['reason' => 'x'])->assertRedirect(route('login'));
        $this->actingAs($review->author)->post(route('moderation.hide', [$this->mall, $review]), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->staffOf($this->shop))->post(route('moderation.hide', [$this->mall, $review]), ['reason' => 'x'])->assertForbidden();
        $this->assertSame(ReviewStatus::Published, $review->fresh()->status);
    }

    public function test_mall_administrators_find_the_desk_from_their_account_page(): void
    {
        $this->actingAs($this->moderator)->get(route('account.show'))->assertSee('Malls you administer')->assertSee($this->desk(), false);
        $this->actingAs(User::factory()->create())->get(route('account.show'))->assertDontSee('Malls you administer');
    }
}
