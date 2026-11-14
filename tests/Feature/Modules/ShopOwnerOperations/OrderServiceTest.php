<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\ShopOwnerOperations\Enums\OrderStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ListingStock;
use App\Modules\ShopOwnerOperations\Models\PurchaseReceipt;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Services\DiscountAllocator;
use App\Modules\ShopOwnerOperations\Services\OrderService;
use App\Modules\ShopOwnerOperations\Services\StockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private User $customer;

    private ShopProduct $coffee;

    private ShopProduct $tea;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->customer = User::factory()->create();
        $this->coffee = ShopProduct::factory()->for($this->shop)->create(['price_amount' => '120.00', 'seller_sku' => 'COF']);
        $this->tea = ShopProduct::factory()->for($this->shop)->create(['price_amount' => '45.50', 'seller_sku' => 'TEA']);
        $this->orders = app(OrderService::class);
    }

    private function staffWith(ShopFunction $function, ?Shop $shop = null): User
    {
        $user = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($user, $shop ?? $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => $function]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function place(array $overrides = []): ShopOrder
    {
        return $this->orders->place($this->customer, $this->shop, [
            'items' => [['shop_product_id' => $this->coffee->getKey(), 'quantity' => 2], ['shop_product_id' => $this->tea->getKey(), 'quantity' => 1]],
            'kind' => 'click_and_collect',
            'pickup_by' => now()->addHours(3),
            'note' => 'Please pack separately',
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

    public function test_placing_an_order_snapshots_lines_and_prices_and_tells_the_owner(): void
    {
        Notification::fake();
        $order = $this->place();

        $this->assertSame(OrderStatus::Requested, $order->status);
        $this->assertMatchesRegularExpression('/^ORD-[A-Z2-9]{8}$/', $order->order_number);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $order->pickup_code);
        $this->assertSame('285.50', (string) $order->total_amount);
        $this->assertSame('0.00', (string) $order->discount_amount);
        $this->assertCount(2, $order->items);
        $this->assertSame([240.0, 45.5], $order->items->map(fn ($item) => (float) $item->line_total_amount)->all());
        $this->assertSame(['requested'], $order->events->pluck('to_status')->all());
        Notification::assertSentTo($this->owner, SystemNotice::class);

        $this->coffee->update(['price_amount' => '999.00']);
        $this->assertSame('120.00', (string) $order->fresh()->items->first()->unit_price_amount);
    }

    public function test_invalid_orders_are_refused_and_save_nothing(): void
    {
        $other = ShopProduct::factory()->for(Shop::factory()->create())->create();
        $archived = ShopProduct::factory()->for($this->shop)->archived()->create();
        $soldOut = ShopProduct::factory()->for($this->shop)->create(['availability_status' => 'unavailable']);
        $line = fn (ShopProduct $listing, int|string $quantity = 1): array => ['shop_product_id' => $listing->getKey(), 'quantity' => $quantity];

        $cases = [
            'order.invalid_lines' => ['items' => []],
            'order.listing_unavailable#other' => ['items' => [$line($other)]],
            'order.listing_unavailable#archived' => ['items' => [$line($archived)]],
            'order.listing_unavailable#unknown' => ['items' => [['shop_product_id' => 'not-a-uuid', 'quantity' => 1]]],
            'order.out_of_stock' => ['items' => [$line($soldOut)]],
            'order.duplicate_listing' => ['items' => [$line($this->coffee), $line($this->coffee)]],
            'order.invalid_quantity#zero' => ['items' => [$line($this->coffee, 0)]],
            'order.invalid_quantity#many' => ['items' => [$line($this->coffee, OrderService::MAX_LINE_QUANTITY + 1)]],
            'order.invalid_quantity#text' => ['items' => [$line($this->coffee, 'x')]],
            'order.invalid_kind' => ['kind' => 'delivery'],
            'order.invalid_pickup_time#soon' => ['pickup_by' => now()->addMinutes(5)],
            'order.invalid_pickup_time#late' => ['pickup_by' => now()->addDays(8)],
            'order.invalid_pickup_time#junk' => ['pickup_by' => 'tomorrow-ish'],
        ];

        foreach ($cases as $label => $overrides) {
            $this->assertSame(explode('#', $label)[0], $this->rule(fn () => $this->place($overrides)), $label);
        }

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_a_tracked_listing_cannot_be_ordered_beyond_its_stock(): void
    {
        $this->orders = app(OrderService::class);
        app(StockService::class)->receive($this->owner, $this->coffee, 3);

        $this->assertSame('order.out_of_stock', $this->rule(fn () => $this->place(['items' => [['shop_product_id' => $this->coffee->getKey(), 'quantity' => 4]]])));
        $this->assertSame(OrderStatus::Requested, $this->place(['items' => [['shop_product_id' => $this->coffee->getKey(), 'quantity' => 3]]])->status);
    }

    public function test_orders_need_an_active_shop_an_active_customer_and_stay_within_the_open_limit(): void
    {
        foreach (range(1, OrderService::MAX_OPEN_PER_CUSTOMER) as $i) {
            $this->place();
        }

        $this->assertSame('order.too_many_open', $this->rule(fn () => $this->place()));

        $other = User::factory()->create();
        $this->shop->update(['status' => 'closed']);
        $this->assertSame('order.shop_not_operational', $this->rule(fn () => $this->orders->place($other, $this->shop, ['items' => [['shop_product_id' => $this->coffee->getKey(), 'quantity' => 1]], 'kind' => 'reservation', 'pickup_by' => now()->addHours(2)])));

        $this->shop->update(['status' => 'active']);
        $this->customer->forceFill(['status' => 'suspended'])->save();
        $this->expectException(AuthorizationException::class);
        $this->place();
    }

    public function test_the_full_lifecycle_records_the_sale_at_the_order_prices(): void
    {
        app(StockService::class)->receive($this->owner, $this->coffee, 10);
        $cashier = $this->staffWith(ShopFunction::Cashier);
        $order = $this->place();

        Notification::fake();
        $this->orders->accept($cashier, $order);
        $this->orders->markReady($cashier, $order);
        Notification::assertSentTo($this->customer, SystemNotice::class, 2);

        $this->coffee->update(['price_amount' => '500.00']);
        $this->orders->complete($cashier, $order, strtolower($order->pickup_code));

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $purchase = Purchase::query()->sole();
        $this->assertSame($purchase->getKey(), $order->purchase_id);
        $this->assertSame($this->customer->getKey(), $purchase->buyer_user_id);
        $this->assertSame($order->order_number, $purchase->external_receipt_ref);
        $this->assertSame('285.50', (string) $purchase->total_amount);
        $this->assertSame('120.00', (string) $purchase->items->first()->unit_price_amount);
        $this->assertSame(8, ListingStock::query()->sole()->on_hand);
        $this->assertMatchesRegularExpression('/^RC-\d{8}-[A-Z0-9]{6}$/', PurchaseReceipt::query()->sole()->receipt_number);
        $this->assertSame(['requested', 'accepted', 'ready', 'completed'], $order->events->pluck('to_status')->all());
        $this->assertNotNull($order->completed_at);
    }

    public function test_a_wrong_pickup_code_hands_nothing_over(): void
    {
        $cashier = $this->staffWith(ShopFunction::Cashier);
        $order = $this->place();
        $this->orders->accept($cashier, $order);

        $this->assertSame('order.wrong_pickup_code', $this->rule(fn () => $this->orders->complete($cashier, $order, 'WRONG1')));
        $this->assertSame(OrderStatus::Accepted, $order->fresh()->status);
        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_a_completion_that_cannot_be_covered_by_stock_rolls_everything_back(): void
    {
        app(StockService::class)->receive($this->owner, $this->coffee, 5);
        $order = $this->place();
        $cashier = $this->staffWith(ShopFunction::Cashier);
        $this->orders->accept($cashier, $order);
        app(StockService::class)->adjustTo($this->owner, $this->coffee, 1, 'Dropped a crate');

        $this->assertSame('stock.insufficient', $this->rule(fn () => $this->orders->complete($cashier, $order, $order->pickup_code)));

        $this->assertSame(OrderStatus::Accepted, $order->fresh()->status);
        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame(0, PurchaseReceipt::query()->count());
        $this->assertSame(1, ListingStock::query()->sole()->on_hand);
    }

    public function test_statuses_only_move_along_allowed_transitions(): void
    {
        $cashier = $this->staffWith(ShopFunction::Cashier);
        $order = $this->place();

        $this->assertSame('order.not_collectable', $this->rule(fn () => $this->orders->complete($cashier, $order, $order->pickup_code)));
        $this->assertSame('order.invalid_transition', $this->rule(fn () => $this->orders->markReady($cashier, $order)));

        $this->orders->reject($cashier, $order, 'Out of that blend');
        $this->assertSame(OrderStatus::Rejected, $order->fresh()->status);
        $this->assertSame('Out of that blend', $order->fresh()->shop_note);
        $this->assertSame('order.invalid_transition', $this->rule(fn () => $this->orders->accept($cashier, $order)));
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $order = $this->place();

        $this->assertSame('order.reason_required', $this->rule(fn () => $this->orders->reject($this->owner, $order, ' ')));
        $this->assertSame(OrderStatus::Requested, $order->fresh()->status);
    }

    public function test_only_the_customer_can_cancel_and_not_after_collection(): void
    {
        $order = $this->place();

        try {
            $this->orders->cancel(User::factory()->create(), $order);
            $this->fail('Another customer must not cancel the order.');
        } catch (AuthorizationException) {
            $this->assertSame(OrderStatus::Requested, $order->fresh()->status);
        }

        $this->orders->cancel($this->customer, $order);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame('order.invalid_transition', $this->rule(fn () => $this->orders->cancel($this->customer, $order)));
    }

    public function test_only_the_orders_area_of_this_shop_may_process_orders(): void
    {
        $order = $this->place();
        $others = [
            'catalogue editor' => $this->staffWith(ShopFunction::CatalogueEditor),
            'customer' => $this->customer,
            'other shop owner' => tap(User::factory()->create(), fn (User $user) => app(MembershipService::class)->grantShopRole($user, Shop::factory()->create(), RoleCode::ShopOwner)),
        ];

        foreach ($others as $label => $user) {
            try {
                $this->orders->accept($user, $order);
                $this->fail("$label must be refused.");
            } catch (AuthorizationException) {
                $this->assertSame(OrderStatus::Requested, $order->fresh()->status, $label);
            }
        }

        $this->orders->accept($this->staffWith(ShopFunction::Manager), $order);
        $this->assertSame(OrderStatus::Accepted, $order->fresh()->status);
    }

    public function test_uncollected_orders_expire_after_the_grace_period(): void
    {
        $old = $this->place();
        $recent = $this->place();
        $done = ShopOrder::factory()->status('completed')->for($this->shop)->create(['pickup_by' => now()->subDays(10)]);
        ShopOrder::query()->whereKey($old->getKey())->update(['pickup_by' => now()->subHours(OrderService::EXPIRY_GRACE_HOURS + 1)]);
        ShopOrder::query()->whereKey($recent->getKey())->update(['pickup_by' => now()->subHours(2)]);

        $this->artisan('orders:expire-overdue')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $old->fresh()->status);
        $this->assertSame(OrderStatus::Requested, $recent->fresh()->status);
        $this->assertSame(OrderStatus::Completed, $done->fresh()->status);
        $this->assertSame(0, $this->orders->expireOverdue());
    }

    public function test_order_lines_and_frozen_fields_cannot_change(): void
    {
        $order = $this->place();

        $this->expectException(\LogicException::class);
        $order->update(['total_amount' => '1.00']);
    }

    public function test_a_discount_is_split_in_whole_cents_that_add_up(): void
    {
        $this->assertSame([3333, 3333, 3334], DiscountAllocator::allocate(10000, [10000, 10000, 10000]));
        $this->assertSame([1000, 0], DiscountAllocator::allocate(1000, [1000, 0]));
        $this->assertSame([500, 500], DiscountAllocator::allocate(99999, [500, 500]));
        $this->assertSame([0, 0], DiscountAllocator::allocate(0, [100, 100]));
        $this->assertSame([0], DiscountAllocator::allocate(50, [0]));
        $this->assertSame(1234, array_sum(DiscountAllocator::allocate(1234, [701, 299, 1500, 3])));
    }
}
