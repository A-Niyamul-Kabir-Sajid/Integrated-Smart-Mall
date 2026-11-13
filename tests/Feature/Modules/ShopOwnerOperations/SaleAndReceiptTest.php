<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Models\VerificationToken;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ListingStock;
use App\Modules\ShopOwnerOperations\Models\PurchaseReceipt;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleAndReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private User $cashier;

    private User $customer;

    private ShopProduct $coffee;

    private ShopProduct $tea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['name' => 'Cafe Brew']);
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->cashier = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($this->cashier, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::Cashier]);
        $this->customer = User::factory()->create(['email' => 'regular@example.com']);
        $this->coffee = ShopProduct::factory()->for($this->shop)->create(['price_amount' => '100.00', 'seller_sku' => 'COF']);
        $this->tea = ShopProduct::factory()->for($this->shop)->create(['price_amount' => '40.00', 'seller_sku' => 'TEA']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function saleForm(array $overrides = []): array
    {
        return [
            'buyer_contact' => 'regular@example.com',
            'receipt_ref' => '',
            'lines' => [
                $this->coffee->getKey() => ['quantity' => 2, 'unit_price_amount' => '', 'line_discount_amount' => '10'],
                $this->tea->getKey() => ['quantity' => 0],
            ],
            ...$overrides,
        ];
    }

    public function test_the_cashier_records_a_sale_for_a_known_customer_and_gets_a_receipt(): void
    {
        app(StockService::class)->receive($this->owner, $this->coffee, 5);

        $this->actingAs($this->cashier)->get(route('workspace.sales.create', $this->shop))->assertOk()->assertSee('Record a sale');
        $this->post(route('workspace.sales.store', $this->shop), $this->saleForm())->assertRedirect()->assertSessionHas('status');

        $purchase = Purchase::query()->with('items')->sole();
        $this->assertSame($this->customer->getKey(), $purchase->buyer_user_id);
        $this->assertSame('190.00', (string) $purchase->total_amount);
        $this->assertCount(1, $purchase->items);
        $this->assertMatchesRegularExpression('/^W-\d{8}-[A-Z0-9]{6}$/', $purchase->external_receipt_ref);
        $this->assertSame(3, ListingStock::query()->sole()->on_hand);
        $receipt = PurchaseReceipt::query()->sole();

        $this->get(route('workspace.sales.index', $this->shop))->assertSee($purchase->external_receipt_ref);
        $this->get(route('workspace.sales.show', [$this->shop, $purchase]))->assertOk()->assertSee($receipt->receipt_number)->assertSee('Issue review code');
        $this->get(route('workspace.sales.receipt', [$this->shop, $purchase]))->assertOk()->assertSee($receipt->receipt_number)->assertSee('৳190.00')->assertSee('Cafe Brew');
    }

    public function test_a_walk_in_sale_needs_no_customer_and_cannot_get_review_codes(): void
    {
        $this->actingAs($this->owner)->post(route('workspace.sales.store', $this->shop), $this->saleForm(['buyer_contact' => '', 'receipt_ref' => 'POS-77']))->assertRedirect();

        $purchase = Purchase::query()->sole();
        $this->assertNull($purchase->buyer_user_id);
        $this->assertSame('POS-77', $purchase->external_receipt_ref);

        $item = $purchase->items->first();
        $this->post(route('workspace.sales.review-code', [$this->shop, $purchase, $item]))->assertSessionHasErrors('business');
        $this->assertSame(0, VerificationToken::query()->count());
        $this->post(route('workspace.sales.store', $this->shop), $this->saleForm(['receipt_ref' => 'POS-77', 'buyer_contact' => '']))->assertSessionHasErrors('business');
    }

    public function test_invalid_sales_save_nothing(): void
    {
        app(StockService::class)->receive($this->owner, $this->coffee, 1);
        $this->actingAs($this->cashier);

        $this->post(route('workspace.sales.store', $this->shop), $this->saleForm(['buyer_contact' => 'nobody@example.com']))->assertSessionHasErrors('business');
        $this->post(route('workspace.sales.store', $this->shop), $this->saleForm())->assertSessionHasErrors('business');
        $this->post(route('workspace.sales.store', $this->shop), $this->saleForm(['lines' => [$this->coffee->getKey() => ['quantity' => 0]]]))->assertSessionHasErrors('business');
        $this->post(route('workspace.sales.store', $this->shop), $this->saleForm(['lines' => [$this->coffee->getKey() => ['quantity' => 1, 'line_discount_amount' => '500']]]))->assertSessionHasErrors('business');
        $this->post(route('workspace.sales.store', $this->shop), ['lines' => 'x'])->assertSessionHasErrors('lines');

        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame(0, PurchaseReceipt::query()->count());
        $this->assertSame(1, ListingStock::query()->sole()->on_hand);
    }

    public function test_a_review_code_is_issued_once_and_shown_once(): void
    {
        $this->actingAs($this->cashier)->post(route('workspace.sales.store', $this->shop), $this->saleForm());
        $purchase = Purchase::query()->sole();
        $item = $purchase->items->first();

        $this->post(route('workspace.sales.review-code', [$this->shop, $purchase, $item]))->assertRedirect()->assertSessionHas('review_code');
        $secret = session('review_code.secret');
        $this->assertSame(64, strlen(VerificationToken::query()->sole()->token_digest));
        $this->assertStringNotContainsString($secret, (string) VerificationToken::query()->sole()->token_digest);

        $this->get(route('workspace.sales.show', [$this->shop, $purchase]))->assertSee($secret);
        $this->get(route('workspace.sales.show', [$this->shop, $purchase]))->assertSee('Code issued')->assertDontSee($secret);
        $this->post(route('workspace.sales.review-code', [$this->shop, $purchase, $item]))->assertSessionHasErrors('business');
    }

    public function test_voiding_a_sale_puts_the_goods_back_and_stops_its_codes(): void
    {
        app(StockService::class)->receive($this->owner, $this->coffee, 5);
        $this->actingAs($this->cashier)->post(route('workspace.sales.store', $this->shop), $this->saleForm());
        $purchase = Purchase::query()->sole();
        $this->assertSame(3, ListingStock::query()->sole()->on_hand);

        $this->post(route('workspace.sales.void', [$this->shop, $purchase]), ['reason' => ''])->assertSessionHasErrors('business');
        $this->post(route('workspace.sales.void', [$this->shop, $purchase]), ['reason' => 'Rung up twice'])->assertRedirect();

        $this->assertSame(PurchaseStatus::Voided, $purchase->fresh()->status);
        $this->assertSame(5, ListingStock::query()->sole()->on_hand);
        $this->post(route('workspace.sales.void', [$this->shop, $purchase]), ['reason' => 'Again'])->assertSessionHasErrors('business');
        $this->assertSame(5, ListingStock::query()->sole()->on_hand);
    }

    public function test_the_customer_reads_only_their_own_purchases_and_receipts(): void
    {
        $this->actingAs($this->cashier)->post(route('workspace.sales.store', $this->shop), $this->saleForm());
        $this->post(route('workspace.sales.store', $this->shop), $this->saleForm(['buyer_contact' => '', 'receipt_ref' => 'WALKIN-1']));
        $mine = Purchase::query()->where('buyer_user_id', $this->customer->getKey())->sole();
        $walkIn = Purchase::query()->where('external_receipt_ref', 'WALKIN-1')->sole();

        $this->actingAs($this->customer)->get(route('account.purchases.index'))->assertOk()->assertSee('Cafe Brew')->assertSee('৳190.00');
        $this->get(route('account.purchases.show', $mine))->assertOk()->assertSee('RC-');
        $this->get(route('account.purchases.show', $walkIn))->assertNotFound();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('account.purchases.show', $mine))->assertNotFound();
        $this->get(route('account.purchases.index'))->assertDontSee('Cafe Brew');
        $this->get(route('account.purchases.show', 'not-a-uuid'))->assertNotFound();
    }

    public function test_counter_sales_are_closed_to_guests_editors_customers_and_other_shops(): void
    {
        $this->actingAs($this->cashier)->post(route('workspace.sales.store', $this->shop), $this->saleForm());
        $purchase = Purchase::query()->sole();

        $editor = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($editor, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::CatalogueEditor]);
        $otherShop = Shop::factory()->create();
        $otherOwner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);

        auth()->logout();
        $this->get(route('workspace.sales.index', $this->shop))->assertRedirect(route('login'));

        foreach ([$this->customer, $editor, $otherOwner] as $user) {
            $this->actingAs($user)->get(route('workspace.sales.index', $this->shop))->assertForbidden();
            $this->get(route('workspace.sales.show', [$this->shop, $purchase]))->assertForbidden();
            $this->post(route('workspace.sales.store', $this->shop), $this->saleForm())->assertForbidden();
        }

        $this->actingAs($otherOwner)->get(route('workspace.sales.show', [$otherShop, $purchase]))->assertNotFound();
        $this->get(route('workspace.sales.receipt', [$otherShop, $purchase]))->assertNotFound();
        $this->assertSame(1, Purchase::query()->count());
    }
}
