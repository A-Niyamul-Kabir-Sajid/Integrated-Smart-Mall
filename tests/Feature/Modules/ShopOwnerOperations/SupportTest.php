<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Enums\SupportStatus;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Models\SupportMessage;
use App\Modules\ShopOwnerOperations\Models\SupportThread;
use App\Modules\ShopOwnerOperations\Services\SupportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private User $cashier;

    private User $customer;

    private SupportService $support;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['name' => 'Cafe Brew']);
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->cashier = $this->staffWith(ShopFunction::Cashier);
        $this->customer = User::factory()->create();
        $this->support = app(SupportService::class);
    }

    private function staffWith(ShopFunction $function): User
    {
        $user = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($user, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => $function]);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function contactUrl(): array
    {
        return ['publicMall' => $this->shop->mall->slug, 'publicShop' => $this->shop->slug];
    }

    private function open(array $overrides = []): SupportThread
    {
        return $this->support->open($this->customer, $this->shop, ['subject' => 'Wrong item', 'kind' => 'complaint', 'body' => 'I received tea instead of coffee.', ...$overrides]);
    }

    private function rule(callable $action): string
    {
        try {
            $action();
        } catch (BusinessRuleViolation $violation) {
            return $violation->rule;
        }

        $this->fail('Expected a business rule violation.');
    }

    public function test_a_customer_writes_to_the_shop_and_the_conversation_goes_both_ways(): void
    {
        Notification::fake();
        $this->actingAs($this->customer)->get(route('shops.show', $this->contactUrl()))->assertSee('Contact the shop');
        $this->get(route('support.create', $this->contactUrl()))->assertOk()->assertSee('Contact Cafe Brew');

        $this->post(route('support.store', $this->contactUrl()), ['subject' => 'Wrong item', 'kind' => 'complaint', 'body' => 'I received tea instead of coffee.'])->assertRedirect();
        $thread = SupportThread::query()->sole();
        Notification::assertSentTo($this->owner, SystemNotice::class);
        $this->get(route('support.show', $thread))->assertOk()->assertSee('I received tea instead of coffee.')->assertSee('Waiting for the shop');

        $this->actingAs($this->cashier)->get(route('workspace.support.index', $this->shop))->assertSee('Wrong item');
        $this->get(route('workspace.support.show', [$this->shop, $thread]))->assertOk()->assertSee($this->customer->display_name);
        $this->post(route('workspace.support.reply', [$this->shop, $thread]), ['body' => 'So sorry, bring the receipt and we replace it.'])->assertRedirect();
        $this->assertSame(SupportStatus::Answered, $thread->fresh()->status);
        Notification::assertSentTo($this->customer, SystemNotice::class);

        $this->actingAs($this->customer)->get(route('support.show', $thread))->assertSee('So sorry, bring the receipt')->assertSee('Answered');
        $this->post(route('support.reply', $thread), ['body' => 'Thank you, coming at 5.'])->assertRedirect();
        $this->assertSame(SupportStatus::Open, $thread->fresh()->status);
        $this->assertSame(3, SupportMessage::query()->count());
        $this->get(route('support.index'))->assertSee('Wrong item');
    }

    public function test_invalid_messages_are_refused(): void
    {
        $this->assertSame('support.invalid_kind', $this->rule(fn () => $this->open(['kind' => 'rant'])));
        $this->assertSame('support.invalid_subject', $this->rule(fn () => $this->open(['subject' => 'x'])));
        $this->assertSame('support.invalid_body', $this->rule(fn () => $this->open(['body' => '  '])));
        $this->assertSame('support.invalid_body', $this->rule(fn () => $this->open(['body' => str_repeat('a', SupportService::BODY_MAX + 1)])));
        $this->assertSame(0, SupportThread::query()->count());

        $this->actingAs($this->customer)->post(route('support.store', $this->contactUrl()), ['subject' => '', 'kind' => 'question', 'body' => ''])->assertSessionHasErrors(['subject', 'body']);
    }

    public function test_the_number_of_open_conversations_is_limited(): void
    {
        foreach (range(1, SupportService::MAX_OPEN_PER_CUSTOMER) as $i) {
            $this->open(['subject' => "Question $i"]);
        }

        $this->assertSame('support.too_many_open', $this->rule(fn () => $this->open()));

        $this->support->close($this->customer, SupportThread::query()->first());
        $this->assertSame(SupportStatus::Open, $this->open()->status);
    }

    public function test_a_closed_conversation_takes_no_more_messages(): void
    {
        $thread = $this->open();
        $this->support->close($this->cashier, $thread);

        $this->assertSame(SupportStatus::Closed, $thread->fresh()->status);
        $this->assertNotNull($thread->fresh()->closed_at);
        $this->assertSame('support.closed', $this->rule(fn () => $this->support->customerReply($this->customer, $thread, 'Hello?')));
        $this->assertSame('support.closed', $this->rule(fn () => $this->support->staffReply($this->cashier, $thread, 'Hi')));
        $this->assertSame('support.closed', $this->rule(fn () => $this->support->close($this->customer, $thread)));
        $this->assertSame(1, SupportMessage::query()->count());
    }

    public function test_threads_have_a_message_limit(): void
    {
        $thread = $this->open();
        SupportMessage::query()->insert(collect(range(2, SupportService::MAX_MESSAGES))->map(fn ($i): array => [
            'support_message_id' => (string) Str::uuid(), 'support_thread_id' => $thread->getKey(), 'author_user_id' => $this->customer->getKey(),
            'author_side' => 'customer', 'body' => "m$i", 'created_at' => now(),
        ])->all());

        $this->assertSame('support.too_long', $this->rule(fn () => $this->support->customerReply($this->customer, $thread, 'one more')));
    }

    public function test_only_the_thread_owner_and_support_staff_of_the_shop_take_part(): void
    {
        $thread = $this->open();
        $stranger = User::factory()->create();
        $editor = $this->staffWith(ShopFunction::CatalogueEditor);
        $otherOwner = User::factory()->create();
        $otherShop = Shop::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);

        foreach ([$stranger, $editor, $otherOwner] as $user) {
            foreach (['staffReply', 'customerReply'] as $method) {
                try {
                    $this->support->{$method}($user, $thread, 'I am not allowed');
                    $this->fail("$method must be refused.");
                } catch (AuthorizationException) {
                    $this->assertSame(1, SupportMessage::query()->count());
                }
            }
        }
    }

    public function test_http_isolation_between_customers_and_shops(): void
    {
        $thread = $this->open();
        $stranger = User::factory()->create();
        $otherOwner = User::factory()->create();
        $otherShop = Shop::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);

        $this->get(route('support.show', $thread))->assertRedirect(route('login'));
        $this->get(route('workspace.support.index', $this->shop))->assertRedirect(route('login'));

        $this->actingAs($stranger)->get(route('support.show', $thread))->assertNotFound();
        $this->post(route('support.reply', $thread), ['body' => 'hi'])->assertNotFound();
        $this->post(route('support.close', $thread))->assertNotFound();
        $this->get(route('support.index'))->assertDontSee('Wrong item');
        $this->get(route('workspace.support.index', $this->shop))->assertForbidden();

        $this->actingAs($otherOwner)->get(route('workspace.support.index', $this->shop))->assertForbidden();
        $this->get(route('workspace.support.show', [$otherShop, $thread]))->assertNotFound();
        $this->post(route('workspace.support.reply', [$otherShop, $thread]), ['body' => 'hi'])->assertNotFound();

        $editor = $this->staffWith(ShopFunction::CatalogueEditor);
        $this->actingAs($editor)->get(route('workspace.support.index', $this->shop))->assertForbidden();
        $this->assertSame(1, SupportMessage::query()->count());
    }

    public function test_messages_and_threads_are_append_only(): void
    {
        $thread = $this->open();
        $message = SupportMessage::query()->sole();

        try {
            $message->update(['body' => 'edited']);
            $this->fail('A message must not be editable.');
        } catch (LogicException) {
            $this->assertSame('I received tea instead of coffee.', $message->fresh()->body);
        }

        $this->expectException(LogicException::class);
        $thread->update(['subject' => 'Changed subject']);
    }

    public function test_a_suspended_shop_can_still_be_written_to_but_not_answer(): void
    {
        $this->shop->update(['status' => 'closed']);
        $thread = $this->open(['subject' => 'Is it reopening?']);

        $this->actingAs($this->owner)->get(route('workspace.support.index', $this->shop))->assertForbidden();

        $this->shop->update(['status' => 'draft']);
        $this->assertSame('support.shop_unavailable', $this->rule(fn () => $this->open()));
        $this->assertSame(1, SupportThread::query()->count());
        $this->assertSame(SupportStatus::Open, $thread->fresh()->status);
    }
}
