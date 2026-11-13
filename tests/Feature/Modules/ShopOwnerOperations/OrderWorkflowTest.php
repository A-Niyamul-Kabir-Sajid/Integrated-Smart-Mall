<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ShopOwnerOperations\Enums\OrderStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $customer;

    private User $cashier;

    private ShopProduct $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['name' => 'Cafe Brew']);
        $this->customer = User::factory()->create();
        $this->cashier = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($this->cashier, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::Cashier]);
        $this->coffee = ShopProduct::factory()->for($this->shop)->create(['price_amount' => '120.00', 'seller_sku' => 'COF']);
    }

    private function orderUrl(): array
    {
        return ['publicMall' => $this->shop->mall->slug, 'publicShop' => $this->shop->slug];
    }

    private function placeOrder(?User $customer = null): ShopOrder
    {
        return app(OrderService::class)->place($customer ?? $this->customer, $this->shop, [
            'items' => [['shop_product_id' => $this->coffee->getKey(), 'quantity' => 2]],
            'kind' => 'reservation',
            'pickup_by' => now()->addHours(4),
        ]);
    }

    public function test_the_customer_orders_from_the_shop_page_and_follows_the_order(): void
    {
        $this->get(route('orders.create', $this->orderUrl()))->assertRedirect(route('login'));
        $this->get(route('shops.show', $this->orderUrl()))->assertOk()->assertSee('Sign in to order');

        $this->actingAs($this->customer)->get(route('shops.show', $this->orderUrl()))->assertSee('Order from this shop');
        $this->get(route('orders.create', [...$this->orderUrl(), 'listing' => $this->coffee->getKey()]))->assertOk()->assertSee('value="1"', false)->assertSee('৳120.00');

        $this->post(route('orders.store', $this->orderUrl()), [
            'quantities' => [$this->coffee->getKey() => 2],
            'kind' => 'click_and_collect',
            'pickup_by' => now()->addHours(5)->format('Y-m-d\TH:i'),
            'note' => 'Extra hot',
        ])->assertRedirect()->assertSessionHas('status');

        $order = ShopOrder::query()->sole();
        $this->get(route('orders.show', $order))->assertOk()->assertSee($order->order_number)->assertSee($order->pickup_code)->assertSee('Waiting for the shop')->assertSee('Cancel this order');
        $this->get(route('orders.index'))->assertSee($order->order_number);

        $this->post(route('orders.cancel', $order))->assertRedirect();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->get(route('orders.show', $order))->assertDontSee('Cancel this order')->assertDontSee('Your pickup code');
    }

    public function test_invalid_order_forms_are_refused(): void
    {
        $this->actingAs($this->customer);
        $valid = ['quantities' => [$this->coffee->getKey() => 1], 'kind' => 'reservation', 'pickup_by' => now()->addHours(5)->format('Y-m-d\TH:i')];

        $this->post(route('orders.store', $this->orderUrl()), [...$valid, 'quantities' => [$this->coffee->getKey() => 0]])->assertSessionHasErrors('business');
        $this->post(route('orders.store', $this->orderUrl()), [...$valid, 'kind' => 'delivery'])->assertSessionHasErrors('kind');
        $this->post(route('orders.store', $this->orderUrl()), [...$valid, 'pickup_by' => ''])->assertSessionHasErrors('pickup_by');
        $this->post(route('orders.store', $this->orderUrl()), [...$valid, 'pickup_by' => now()->addMinutes(2)->format('Y-m-d\TH:i')])->assertSessionHasErrors('business');
        $this->post(route('orders.store', $this->orderUrl()), [...$valid, 'quantities' => [$this->coffee->getKey() => 999]])->assertSessionHasErrors('quantities.'.$this->coffee->getKey());

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_another_customer_cannot_see_or_cancel_the_order(): void
    {
        $order = $this->placeOrder();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('orders.show', $order))->assertNotFound();
        $this->post(route('orders.cancel', $order))->assertNotFound();
        $this->get(route('orders.index'))->assertDontSee($order->order_number);
        $this->assertSame(OrderStatus::Requested, $order->fresh()->status);
    }

    public function test_the_cashier_works_the_order_inbox_to_a_recorded_sale(): void
    {
        $order = $this->placeOrder();
        $inbox = route('workspace.orders.index', $this->shop);

        $this->actingAs($this->cashier)->get($inbox)->assertOk()->assertSee($order->order_number);
        $this->get(route('workspace.orders.index', [$this->shop, 'status' => 'accepted']))->assertDontSee($order->order_number);
        $this->get(route('workspace.orders.show', [$this->shop, $order]))->assertOk()->assertSee('Accept')->assertSee('Decline');

        $this->post(route('workspace.orders.accept', [$this->shop, $order]))->assertRedirect()->assertSessionHas('status');
        $this->post(route('workspace.orders.ready', [$this->shop, $order]))->assertRedirect();

        $this->post(route('workspace.orders.complete', [$this->shop, $order]), ['pickup_code' => 'NOPE12'])->assertSessionHasErrors('business');
        $this->post(route('workspace.orders.complete', [$this->shop, $order]), [])->assertSessionHasErrors('pickup_code');
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);

        $this->post(route('workspace.orders.complete', [$this->shop, $order]), ['pickup_code' => $order->pickup_code])->assertRedirect();
        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);

        $this->get(route('workspace.orders.show', [$this->shop, $order]))->assertSee('Open the recorded sale and receipt');
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Open your receipt');
        $this->get(route('account.purchases.show', $order->purchase_id))->assertOk()->assertSee('RC-')->assertSee('৳240.00');
    }

    public function test_declining_shows_the_reason_to_the_customer(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->cashier)->post(route('workspace.orders.reject', [$this->shop, $order]), ['reason' => 'x'])->assertSessionHasErrors('business');
        $this->post(route('workspace.orders.reject', [$this->shop, $order]), ['reason' => 'We are closing early today.'])->assertRedirect();

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Declined by the shop')->assertSee('We are closing early today.');
    }

    public function test_the_order_inbox_is_closed_to_everyone_else_and_scoped_to_the_shop(): void
    {
        $order = $this->placeOrder();
        $editor = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($editor, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::CatalogueEditor]);
        $otherOwner = User::factory()->create();
        $otherShop = Shop::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);

        $this->get(route('workspace.orders.index', $this->shop))->assertRedirect(route('login'));

        foreach ([$this->customer, $editor, $otherOwner] as $user) {
            $this->actingAs($user)->get(route('workspace.orders.index', $this->shop))->assertForbidden();
            $this->post(route('workspace.orders.accept', [$this->shop, $order]))->assertForbidden();
        }

        $this->actingAs($otherOwner)->get(route('workspace.orders.show', [$otherShop, $order]))->assertNotFound();
        $this->assertSame(OrderStatus::Requested, $order->fresh()->status);
    }

    public function test_a_suspended_shop_takes_no_new_orders_but_customers_can_still_cancel(): void
    {
        $order = $this->placeOrder();
        $this->shop->update(['status' => 'closed']);

        $this->actingAs($this->customer)->post(route('orders.store', $this->orderUrl()), [
            'quantities' => [$this->coffee->getKey() => 1], 'kind' => 'reservation', 'pickup_by' => now()->addHours(5)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('business');
        $this->actingAs($this->cashier)->get(route('workspace.orders.index', $this->shop))->assertForbidden();

        $this->actingAs($this->customer)->post(route('orders.cancel', $order))->assertRedirect();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_the_product_page_offers_an_order_link_for_available_listings_only(): void
    {
        $soldOut = ShopProduct::factory()->for($this->shop)->create(['availability_status' => 'unavailable']);

        $page = $this->get(route('products.show', ['publicMall' => $this->shop->mall->slug, 'publicProduct' => $this->coffee->variant->product->getKey()]));

        $page->assertOk()->assertSee(route('orders.create', [...$this->orderUrl(), 'listing' => $this->coffee->getKey()]), false);
        $this->get(route('products.show', ['publicMall' => $this->shop->mall->slug, 'publicProduct' => $soldOut->variant->product->getKey()]))->assertDontSee('listing='.$soldOut->getKey(), false);
    }
}
