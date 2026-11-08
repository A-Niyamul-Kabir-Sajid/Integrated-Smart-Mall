<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ReviewsModeration\Enums\ReplyStatus;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        $this->staff = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->staff, $this->shop, RoleCode::ShopStaff);
    }

    private function inbox(array $query = []): string
    {
        return route('workspace.reviews.index', [$this->shop, ...$query]);
    }

    public function test_the_inbox_requires_sign_in_and_work_in_shop_for_that_shop(): void
    {
        $this->get($this->inbox())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($this->inbox())->assertForbidden();

        $otherStaff = User::factory()->create();
        app(MembershipService::class)->grantShopRole($otherStaff, Shop::factory()->create(), RoleCode::ShopStaff);
        $this->actingAs($otherStaff)->get($this->inbox())->assertForbidden();

        $this->actingAs($this->staff)->get($this->inbox())->assertOk()->assertSee('No reviews here yet.');
    }

    public function test_staff_see_only_published_reviews_of_their_own_shop_and_can_filter_unanswered(): void
    {
        $answered = Review::factory()->for($this->shop)->create(['body' => 'Answered review text here.']);
        ReviewReply::factory()->for($answered)->create();
        Review::factory()->for($this->shop)->create(['body' => 'Open review waiting for a reply.']);
        Review::factory()->for($this->shop)->hidden()->create(['body' => 'Hidden review must stay out.']);
        Review::factory()->create(['body' => 'Another shop review must stay out.']);

        $this->actingAs($this->staff)->get($this->inbox())
            ->assertSee('Answered review text here.')->assertSee('Open review waiting for a reply.')
            ->assertDontSee('Hidden review must stay out.')->assertDontSee('Another shop review must stay out.');

        $this->actingAs($this->staff)->get($this->inbox(['unanswered' => 1]))
            ->assertSee('Open review waiting for a reply.')->assertDontSee('Answered review text here.');
    }

    public function test_staff_reply_edit_and_remove_a_reply(): void
    {
        $review = Review::factory()->for($this->shop)->create(['body' => 'The customer words stay untouched.']);

        $this->actingAs($this->staff)->post(route('workspace.reviews.reply', [$this->shop, $review]), ['body' => 'Thank you for visiting!'])
            ->assertRedirect()->assertSessionHas('status');
        $reply = ReviewReply::query()->sole();
        $this->assertSame($this->staff->getKey(), $reply->author_user_id);

        $this->actingAs($this->staff)->get($this->inbox())->assertSee('Your reply')->assertSee('Thank you for visiting!');

        $this->actingAs($this->staff)->put(route('workspace.reviews.replies.update', [$this->shop, $reply]), ['body' => 'Thanks, see you again.'])->assertRedirect();
        $this->assertSame('Thanks, see you again.', $reply->fresh()->body);

        $this->actingAs($this->staff)->post(route('workspace.reviews.replies.hide', [$this->shop, $reply]))->assertRedirect();
        $this->assertSame(ReplyStatus::Hidden, $reply->fresh()->status);
        $this->assertSame('The customer words stay untouched.', $review->fresh()->body);
    }

    public function test_a_second_reply_and_an_empty_reply_are_refused(): void
    {
        $review = Review::factory()->for($this->shop)->create();
        ReviewReply::factory()->for($review)->create();

        $this->actingAs($this->staff)->from($this->inbox())->post(route('workspace.reviews.reply', [$this->shop, $review]), ['body' => 'Again'])
            ->assertRedirect($this->inbox())->assertSessionHasErrors('business');

        $fresh = Review::factory()->for($this->shop)->create();
        $this->actingAs($this->staff)->from($this->inbox())->post(route('workspace.reviews.reply', [$this->shop, $fresh]), ['body' => '  '])
            ->assertSessionHasErrors('body');
        $this->assertSame(1, ReviewReply::query()->count());
    }

    public function test_staff_cannot_reach_reviews_or_replies_of_another_shop_through_their_own_shop_url(): void
    {
        $foreign = Review::factory()->create();
        $foreignReply = ReviewReply::factory()->for($foreign)->create(['body' => 'Their own reply text.']);

        $this->actingAs($this->staff)->post(route('workspace.reviews.reply', [$this->shop, $foreign]), ['body' => 'Hijack'])->assertNotFound();
        $this->actingAs($this->staff)->put(route('workspace.reviews.replies.update', [$this->shop, $foreignReply]), ['body' => 'Hijack'])->assertNotFound();
        $this->actingAs($this->staff)->post(route('workspace.reviews.replies.hide', [$this->shop, $foreignReply]))->assertNotFound();

        $this->assertSame('Their own reply text.', $foreignReply->fresh()->body);
        $this->assertSame(ReplyStatus::Published, $foreignReply->fresh()->status);
        $this->assertSame(1, ReviewReply::query()->count());
    }

    public function test_the_workspace_and_account_pages_link_to_the_inbox(): void
    {
        $this->actingAs($this->staff)->get(route('workspace.catalogue.index', $this->shop))->assertSee($this->inbox(), false);
        $this->actingAs($this->staff)->get(route('account.show'))->assertSee($this->inbox(), false);
    }
}
