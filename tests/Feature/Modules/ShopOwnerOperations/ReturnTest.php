<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\ShopOwnerOperations\Enums\ReturnStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ListingStock;
use App\Modules\ShopOwnerOperations\Models\ShopReturn;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Services\ReturnService;
use App\Modules\ShopOwnerOperations\Services\SaleService;
use App\Modules\ShopOwnerOperations\Services\StockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private User $cashier;

    private User $customer;

    private ShopProduct $coffee;

    private ShopProduct $tea;

    private Purchase $purchase;

    private ReturnService $returns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['name' => 'Cafe Brew']);
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->cashier = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($this->cashier, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::Cashier]);
        $this->customer = User::factory()->create(['email' => 'buyer@example.com']);
        $this->coffee = ShopProduct::factory()->for($this->shop)->create(['price_amount' => '100.00']);
        $this->tea = ShopProduct::factory()->for($this->shop)->create(['price_amount' => '40.00']);
        app(StockService::class)->receive($this->owner, $this->coffee, 10);

        $this->purchase = app(SaleService::class)->record($this->cashier, $this->shop, [
            'buyer_contact' => 'buyer@example.com',
            'items' => [
                ['shop_product_id' => $this->coffee->getKey(), 'quantity' => 3, 'line_discount_amount' => '30'],
                ['shop_product_id' => $this->tea->getKey(), 'quantity' => 1],
            ],
        ]);
        $this->returns = app(ReturnService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(array $overrides = [], ?Purchase $purchase = null): ShopReturn
    {
        $coffeeLine = $this->purchase->items->firstWhere('shop_product_id', $this->coffee->getKey());

        return $this->returns->request($this->customer, $purchase ?? $this->purchase, [
            'kind' => 'return',
            'reason' => 'The coffee tasted burnt.',
            'items' => [['purchase_item_id' => $coffeeLine->getKey(), 'quantity' => 1]],
            ...$overrides,
        ]);
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

    public function test_the_buyer_requests_a_return_valued_from_the_sale_line(): void
    {
        Notification::fake();
        $return = $this->request();

        $this->assertSame(ReturnStatus::Requested, $return->status);
        $this->assertMatchesRegularExpression('/^RET-[A-Z0-9]{8}$/', $return->return_number);
        $this->assertSame('90.00', (string) $return->value_amount);
        $this->assertSame(['requested'], $return->events->pluck('to_status')->all());
        Notification::assertSentTo($this->owner, SystemNotice::class);
    }

    public function test_requests_are_validated_against_the_sale(): void
    {
        $coffeeLine = $this->purchase->items->firstWhere('shop_product_id', $this->coffee->getKey());
        $line = fn (int|string $quantity) => ['items' => [['purchase_item_id' => $coffeeLine->getKey(), 'quantity' => $quantity]]];

        $this->assertSame('return.no_lines', $this->rule(fn () => $this->request(['items' => []])));
        $this->assertSame('return.invalid_quantity', $this->rule(fn () => $this->request($line(0))));
        $this->assertSame('return.too_many', $this->rule(fn () => $this->request($line(4))));
        $this->assertSame('return.unknown_line', $this->rule(fn () => $this->request(['items' => [['purchase_item_id' => 'nope', 'quantity' => 1]]])));
        $this->assertSame('return.duplicate_line', $this->rule(fn () => $this->request(['items' => [['purchase_item_id' => $coffeeLine->getKey(), 'quantity' => 1], ['purchase_item_id' => $coffeeLine->getKey(), 'quantity' => 1]]])));
        $this->assertSame('return.reason_required', $this->rule(fn () => $this->request(['reason' => 'bad'])));
        $this->assertSame('return.invalid_kind', $this->rule(fn () => $this->request(['kind' => 'gift'])));

        $this->request($line(2));
        $this->assertSame('return.too_many', $this->rule(fn () => $this->request($line(2))));
        $this->assertSame(1, ShopReturn::query()->count());
    }

    public function test_sales_outside_the_window_or_not_completed_cannot_be_returned(): void
    {
        Purchase::query()->whereKey($this->purchase->getKey())->update(['purchased_at' => now()->subDays(ReturnService::WINDOW_DAYS + 1)]);
        $this->assertSame('return.not_eligible', $this->rule(fn () => $this->request(purchase: $this->purchase->fresh('items'))));

        Purchase::query()->whereKey($this->purchase->getKey())->update(['purchased_at' => now()->subDay(), 'status' => PurchaseStatus::Voided->value]);
        $this->assertSame('return.not_eligible', $this->rule(fn () => $this->request(purchase: $this->purchase->fresh('items'))));
    }

    public function test_only_the_buyer_may_request_and_a_sale_has_at_most_three_open_requests(): void
    {
        try {
            $this->returns->request(User::factory()->create(), $this->purchase, ['kind' => 'return', 'reason' => 'not mine', 'items' => []]);
            $this->fail('A stranger must not request a return.');
        } catch (AuthorizationException) {
            $this->assertSame(0, ShopReturn::query()->count());
        }

        $teaLine = $this->purchase->items->firstWhere('shop_product_id', $this->tea->getKey());
        $coffeeLine = $this->purchase->items->firstWhere('shop_product_id', $this->coffee->getKey());

        foreach ([1, 1, 1] as $ignored) {
            $this->request(['items' => [['purchase_item_id' => $coffeeLine->getKey(), 'quantity' => 1]]]);
        }

        $this->assertSame('return.too_many_open', $this->rule(fn () => $this->request(['items' => [['purchase_item_id' => $teaLine->getKey(), 'quantity' => 1]]])));
    }

    public function test_approving_and_completing_restocks_the_goods_and_leaves_the_sale_intact_for_a_partial_return(): void
    {
        $return = $this->request();
        $this->returns->approve($this->cashier, $return, 'Bring the cup too.');
        $this->assertSame(7, ListingStock::query()->sole()->on_hand);

        $this->returns->complete($this->cashier, $return, restock: true);

        $this->assertSame(ReturnStatus::Completed, $return->fresh()->status);
        $this->assertTrue($return->fresh()->restocked);
        $this->assertSame(8, ListingStock::query()->sole()->on_hand);
        $this->assertSame(PurchaseStatus::Completed, $this->purchase->fresh()->status);
        $this->assertSame(['requested', 'approved', 'completed'], $return->fresh()->events->pluck('to_status')->all());
    }

    public function test_completing_without_restock_leaves_stock_alone(): void
    {
        $return = $this->request();
        $this->returns->approve($this->owner, $return);
        $this->returns->complete($this->owner, $return, restock: false);

        $this->assertSame(7, ListingStock::query()->sole()->on_hand);
        $this->assertFalse($return->fresh()->restocked);
    }

    public function test_returning_every_line_for_a_refund_turns_the_sale_into_a_refunded_sale(): void
    {
        $lines = $this->purchase->items->map(fn ($item) => ['purchase_item_id' => $item->getKey(), 'quantity' => $item->quantity])->all();
        $return = $this->request(['items' => $lines]);

        $this->assertSame('310.00', (string) $return->value_amount);
        $this->returns->approve($this->cashier, $return);
        $this->returns->complete($this->cashier, $return, restock: true);

        $this->assertSame(PurchaseStatus::Refunded, $this->purchase->fresh()->status);
    }

    public function test_an_exchange_never_refunds_the_sale(): void
    {
        $lines = $this->purchase->items->map(fn ($item) => ['purchase_item_id' => $item->getKey(), 'quantity' => $item->quantity])->all();
        $return = $this->request(['items' => $lines, 'kind' => 'exchange']);
        $this->returns->approve($this->cashier, $return);
        $this->returns->complete($this->cashier, $return, restock: true);

        $this->assertSame(PurchaseStatus::Completed, $this->purchase->fresh()->status);
    }

    public function test_declining_cancelling_and_invalid_transitions(): void
    {
        $return = $this->request();

        $this->assertSame('return.reason_required', $this->rule(fn () => $this->returns->reject($this->cashier, $return, ' ')));
        $this->assertSame('return.invalid_transition', $this->rule(fn () => $this->returns->complete($this->cashier, $return, true)));

        $this->returns->reject($this->cashier, $return, 'Used beyond what we accept.');
        $this->assertSame(ReturnStatus::Rejected, $return->fresh()->status);
        $this->assertSame('Used beyond what we accept.', $return->fresh()->decision_note);
        $this->assertSame('return.invalid_transition', $this->rule(fn () => $this->returns->approve($this->cashier, $return)));

        $again = $this->request();
        $this->returns->cancel($this->customer, $again);
        $this->assertSame(ReturnStatus::Cancelled, $again->fresh()->status);

        $quantities = $this->returns->committedQuantities($this->purchase->fresh());
        $this->assertSame(0, $quantities->sum());
    }

    public function test_staff_record_a_counter_return_for_a_walk_in_sale(): void
    {
        $walkIn = app(SaleService::class)->record($this->cashier, $this->shop, ['items' => [['shop_product_id' => $this->coffee->getKey(), 'quantity' => 2]]]);
        $line = $walkIn->items->first();

        $return = $this->returns->recordAtCounter($this->cashier, $walkIn, ['kind' => 'return', 'reason' => 'Customer changed their mind', 'items' => [['purchase_item_id' => $line->getKey(), 'quantity' => 1]]]);

        $this->assertSame(ReturnStatus::Approved, $return->status);
        $this->assertSame(['requested', 'approved'], $return->fresh()->events->pluck('to_status')->all());

        $this->expectException(AuthorizationException::class);
        $this->returns->recordAtCounter($this->customer, $walkIn, ['kind' => 'return', 'reason' => 'Trying my luck', 'items' => [['purchase_item_id' => $line->getKey(), 'quantity' => 1]]]);
    }

    public function test_a_sale_with_returns_cannot_be_voided(): void
    {
        $this->request();

        $this->assertSame('sale.has_returns', $this->rule(fn () => app(SaleService::class)->void($this->cashier, $this->purchase, 'Mistake')));
        $this->assertSame(PurchaseStatus::Completed, $this->purchase->fresh()->status);
    }

    public function test_the_customer_pages_show_the_form_and_follow_the_request(): void
    {
        $this->actingAs($this->customer)->get(route('account.purchases.show', $this->purchase))->assertOk()->assertSee('Return or exchange')->assertSee('left to return');

        $coffeeLine = $this->purchase->items->firstWhere('shop_product_id', $this->coffee->getKey());
        $this->post(route('returns.store', $this->purchase), ['kind' => 'return', 'reason' => 'It leaked everywhere', 'quantities' => [$coffeeLine->getKey() => 1]])
            ->assertRedirect()->assertSessionHas('status');

        $return = ShopReturn::query()->sole();
        $this->get(route('returns.show', $return))->assertOk()->assertSee($return->return_number)->assertSee('৳90.00')->assertSee('Cancel this request');
        $this->get(route('returns.index'))->assertSee($return->return_number);

        $this->post(route('returns.store', $this->purchase), ['kind' => 'return', 'reason' => 'x', 'quantities' => [$coffeeLine->getKey() => 1]])->assertSessionHasErrors('business');
        $this->post(route('returns.cancel', $return))->assertRedirect();
        $this->assertSame(ReturnStatus::Cancelled, $return->fresh()->status);
    }

    public function test_another_customer_cannot_see_or_start_returns_of_this_sale(): void
    {
        $return = $this->request();
        $stranger = User::factory()->create();
        $coffeeLine = $this->purchase->items->firstWhere('shop_product_id', $this->coffee->getKey());

        $this->actingAs($stranger)->get(route('returns.show', $return))->assertNotFound();
        $this->post(route('returns.cancel', $return))->assertNotFound();
        $this->post(route('returns.store', $this->purchase), ['kind' => 'return', 'reason' => 'Not my sale', 'quantities' => [$coffeeLine->getKey() => 1]])->assertNotFound();
        $this->get(route('returns.index'))->assertDontSee($return->return_number);
    }

    public function test_the_shop_works_the_returns_inbox_and_nobody_else_may(): void
    {
        $return = $this->request();

        $this->actingAs($this->cashier)->get(route('workspace.returns.index', $this->shop))->assertOk()->assertSee($return->return_number);
        $this->get(route('workspace.returns.show', [$this->shop, $return]))->assertOk()->assertSee('Approve')->assertSee('Decline');
        $this->post(route('workspace.returns.approve', [$this->shop, $return]), ['note' => 'Come in anytime'])->assertRedirect();
        $this->post(route('workspace.returns.complete', [$this->shop, $return]), ['restock' => '1'])->assertRedirect();
        $this->assertSame(ReturnStatus::Completed, $return->fresh()->status);
        $this->get(route('workspace.returns.show', [$this->shop, $return]))->assertSee('put back into stock');

        $coffeeLine = $this->purchase->items->firstWhere('shop_product_id', $this->coffee->getKey());
        $this->post(route('workspace.returns.store', [$this->shop, $this->purchase]), ['kind' => 'exchange', 'reason' => 'Wrong size', 'quantities' => [$coffeeLine->getKey() => 1]])->assertRedirect();
        $this->assertSame(ReturnStatus::Approved, ShopReturn::query()->latest('created_at')->get()->firstWhere('kind.value', 'exchange')->status);

        $otherOwner = User::factory()->create();
        $otherShop = Shop::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);

        foreach ([$this->customer, $otherOwner] as $user) {
            $this->actingAs($user)->get(route('workspace.returns.index', $this->shop))->assertForbidden();
            $this->post(route('workspace.returns.approve', [$this->shop, $return]))->assertForbidden();
        }

        $this->actingAs($otherOwner)->get(route('workspace.returns.show', [$otherShop, $return]))->assertNotFound();
    }
}
