<?php

namespace Tests\Feature\Modules\PurchaseVerification;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\PurchaseVerification\Models\PurchaseItem;
use App\Modules\PurchaseVerification\Services\PurchaseService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PurchaseServiceTest extends TestCase
{
    use CreatesPurchaseFixtures, RefreshDatabase;

    private function service(): PurchaseService
    {
        return app(PurchaseService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sale(ShopProduct $listing, array $overrides = []): array
    {
        return ['external_receipt_ref' => 'R-1001', 'items' => [['shop_product_id' => $listing->getKey(), 'quantity' => 2]], ...$overrides];
    }

    public function test_staff_record_a_sale_with_snapshots_and_a_computed_total(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $coffee = ShopProduct::factory()->for($shop)->create(['price_amount' => '120.50']);
        $tea = ShopProduct::factory()->for($shop)->create(['price_amount' => '40.00']);

        $purchase = $this->service()->recordPurchase($staff, $shop, [
            'external_receipt_ref' => ' R-1001 ',
            'items' => [
                ['shop_product_id' => $coffee->getKey(), 'quantity' => 2],
                ['shop_product_id' => $tea->getKey(), 'quantity' => 1, 'unit_price_amount' => '35.00', 'line_discount_amount' => '5.00'],
            ],
        ]);

        $this->assertSame(PurchaseStatus::Completed, $purchase->status);
        $this->assertSame('R-1001', $purchase->external_receipt_ref);
        $this->assertSame('BDT', $purchase->currency);
        $this->assertSame('271.00', $purchase->fresh()->total_amount);
        $this->assertNull($purchase->buyer_user_id);
        $this->assertSame([1, 2], $purchase->items->pluck('line_no')->all());
        $this->assertSame(['241.00', '30.00'], $purchase->items->pluck('line_total_amount')->all());
        $this->assertSame('35.00', $purchase->items[1]->unit_price_amount);
        $this->assertSame($coffee->variant->product->name.' - '.$coffee->variant->label, $purchase->items[0]->product_label_snapshot);
        $this->assertSame(['purchase.recorded'], AuditLog::query()->where('action', 'purchase.recorded')->pluck('action')->all());
    }

    public function test_a_known_buyer_is_attached_and_a_matching_total_is_accepted(): void
    {
        $shop = Shop::factory()->create();
        $buyer = User::factory()->create();
        $listing = ShopProduct::factory()->for($shop)->create(['price_amount' => '10.00']);

        $purchase = $this->service()->recordPurchase($this->staffOf($shop), $shop, $this->sale($listing, ['buyer_user_id' => $buyer->getKey(), 'total_amount' => '20.00']));

        $this->assertSame($buyer->getKey(), $purchase->buyer_user_id);
    }

    public function test_history_is_not_rewritten_by_later_catalogue_edits(): void
    {
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->for($shop)->create(['price_amount' => '50.00']);
        $purchase = $this->service()->recordPurchase($this->staffOf($shop), $shop, $this->sale($listing));

        $listing->variant->product->update(['name' => 'Renamed product']);
        $listing->update(['price_amount' => '99.00']);

        $item = $purchase->fresh()->items->first();
        $this->assertSame('50.00', $item->unit_price_amount);
        $this->assertSame('100.00', $purchase->fresh()->total_amount);
        $this->assertStringNotContainsString('Renamed product', $item->product_label_snapshot);
    }

    public function test_only_staff_of_the_shop_may_record_and_read_its_sales(): void
    {
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->for($shop)->create();
        $outsider = User::factory()->create();
        $otherShopStaff = $this->staffOf(Shop::factory()->create());

        foreach ([$outsider, $otherShopStaff] as $user) {
            try {
                $this->service()->recordPurchase($user, $shop, $this->sale($listing));
                $this->fail('Expected the sale to be refused.');
            } catch (AuthorizationException) {
                $this->assertSame(0, Purchase::query()->count());
            }
        }

        $this->service()->recordPurchase($this->staffOf($shop, RoleCode::ShopOwner), $shop, $this->sale($listing));
        $this->expectException(AuthorizationException::class);
        $this->service()->listForShop($otherShopStaff, $shop);
    }

    public function test_a_suspended_staff_account_cannot_record_a_sale(): void
    {
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->for($shop)->create();
        $staff = $this->staffOf($shop);
        $staff->forceFill(['status' => 'suspended'])->save();

        $this->expectException(AuthorizationException::class);
        $this->service()->recordPurchase($staff, $shop, $this->sale($listing));
    }

    public function test_rejects_a_sale_for_a_shop_that_is_not_active(): void
    {
        $shop = Shop::factory()->draft()->create();
        $listing = ShopProduct::factory()->for($shop)->create();

        $this->assertRuleViolated('purchase.shop_not_operational', fn () => $this->service()->recordPurchase($this->staffOf($shop), $shop, $this->sale($listing)));
    }

    public function test_a_receipt_reference_is_recorded_once_per_shop(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $listing = ShopProduct::factory()->for($shop)->create();
        $this->service()->recordPurchase($staff, $shop, $this->sale($listing));

        $this->assertRuleViolated('purchase.duplicate_receipt', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing)));
        $this->assertRuleViolated('purchase.invalid_receipt', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['external_receipt_ref' => '  '])));
        $this->assertSame(1, Purchase::query()->count());

        $otherShop = Shop::factory()->create();
        $otherListing = ShopProduct::factory()->for($otherShop)->create();
        $this->service()->recordPurchase($this->staffOf($otherShop), $otherShop, $this->sale($otherListing));
        $this->assertSame(2, Purchase::query()->count());
    }

    public function test_rejects_totals_that_do_not_add_up_and_bad_amounts(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $listing = ShopProduct::factory()->for($shop)->create(['price_amount' => '10.00']);
        $line = ['shop_product_id' => $listing->getKey(), 'quantity' => 1];

        $this->assertRuleViolated('purchase.total_mismatch', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['total_amount' => '99.00'])));
        $this->assertRuleViolated('purchase.discount_too_large', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['items' => [[...$line, 'line_discount_amount' => '10.01']]])));
        $this->assertRuleViolated('purchase.invalid_amount', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['items' => [[...$line, 'unit_price_amount' => '-1']]])));
        $this->assertRuleViolated('purchase.invalid_amount', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['items' => [[...$line, 'unit_price_amount' => '1.999']]])));
        $this->assertRuleViolated('purchase.invalid_quantity', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['items' => [[...$line, 'quantity' => 0]]])));
        $this->assertRuleViolated('purchase.invalid_quantity', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['items' => [[...$line, 'quantity' => '1.5']]])));
        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_a_free_line_with_an_explicit_zero_price_is_honoured(): void
    {
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->for($shop)->create(['price_amount' => '10.00']);

        $purchase = $this->service()->recordPurchase($this->staffOf($shop), $shop, $this->sale($listing, ['items' => [['shop_product_id' => $listing->getKey(), 'quantity' => 1, 'unit_price_amount' => '0']]]));

        $this->assertSame('0.00', $purchase->fresh()->total_amount);
    }

    public function test_rejects_bad_lines_listings_buyers_and_dates(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $listing = ShopProduct::factory()->for($shop)->create();
        $foreign = ShopProduct::factory()->create();
        $line = ['shop_product_id' => $listing->getKey(), 'quantity' => 1];

        $this->assertRuleViolated('purchase.invalid_lines', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['items' => []])));
        $this->assertRuleViolated('purchase.listing_other_shop', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($foreign)));
        $this->assertRuleViolated('purchase.unknown_listing', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['items' => [['shop_product_id' => 'not-a-uuid', 'quantity' => 1]]])));
        $this->assertRuleViolated('purchase.duplicate_listing', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['items' => [$line, $line]])));
        $this->assertRuleViolated('purchase.unknown_buyer', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['buyer_user_id' => '3f0c8f0e-0000-4000-8000-000000000000'])));
        $this->assertRuleViolated('purchase.future_date', fn () => $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['purchased_at' => now()->addDay()])));
        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_a_failed_line_leaves_no_partial_sale(): void
    {
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->for($shop)->create();

        $this->assertRuleViolated('purchase.invalid_quantity', fn () => $this->service()->recordPurchase($this->staffOf($shop), $shop, $this->sale($listing, ['items' => [
            ['shop_product_id' => $listing->getKey(), 'quantity' => 1],
            ['shop_product_id' => ShopProduct::factory()->for($shop)->create()->getKey(), 'quantity' => 0],
        ]])));

        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame(0, PurchaseItem::query()->count());
    }

    public function test_recorded_sales_and_lines_are_immutable_evidence(): void
    {
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->for($shop)->create(['price_amount' => '50.00']);
        $purchase = $this->service()->recordPurchase($this->staffOf($shop), $shop, $this->sale($listing));
        $item = $purchase->items->first();

        foreach ([
            fn () => $purchase->update(['total_amount' => '1.00']),
            fn () => $purchase->update(['external_receipt_ref' => 'OTHER']),
            fn () => $purchase->update(['buyer_user_id' => User::factory()->create()->getKey()]),
            fn () => $purchase->delete(),
            fn () => $item->update(['quantity' => 9]),
            fn () => $item->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the change to be refused.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame('100.00', $purchase->fresh()->total_amount);
    }

    public function test_a_sale_can_be_voided_or_refunded_once_and_the_change_is_audited(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        $listing = ShopProduct::factory()->for($shop)->create();
        $first = $this->service()->recordPurchase($staff, $shop, $this->sale($listing));
        $second = $this->service()->recordPurchase($staff, $shop, $this->sale($listing, ['external_receipt_ref' => 'R-1002']));

        $this->service()->changeStatus($staff, $first, PurchaseStatus::Voided);
        $this->service()->changeStatus($staff, $second, PurchaseStatus::Refunded);

        $this->assertSame(PurchaseStatus::Voided, $first->fresh()->status);
        $this->assertSame(PurchaseStatus::Refunded, $second->fresh()->status);
        $this->assertRuleViolated('purchase.invalid_transition', fn () => $this->service()->changeStatus($staff, $first, PurchaseStatus::Completed));
        $this->assertRuleViolated('purchase.invalid_transition', fn () => $this->service()->changeStatus($staff, $second, PurchaseStatus::Voided));
        $this->assertSame(2, AuditLog::query()->where('action', 'purchase.status_changed')->count());
    }

    public function test_only_staff_of_the_shop_may_change_a_sale_status(): void
    {
        $shop = Shop::factory()->create();
        $listing = ShopProduct::factory()->for($shop)->create();
        $purchase = $this->service()->recordPurchase($this->staffOf($shop), $shop, $this->sale($listing));

        $this->expectException(AuthorizationException::class);
        $this->service()->changeStatus($this->staffOf(Shop::factory()->create()), $purchase, PurchaseStatus::Voided);
    }

    public function test_shop_staff_see_only_their_own_shops_sales(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();
        $staff = $this->staffOf($shop);
        Purchase::factory()->for($shop)->withLine()->count(2)->create();
        $foreign = Purchase::factory()->for($otherShop)->withLine()->create();

        $page = $this->service()->listForShop($staff, $shop);

        $this->assertSame(2, $page->total());
        $this->assertNotContains($foreign->getKey(), $page->pluck('purchase_id')->all());
        $this->assertSame(1, $page->first()->items_count);

        $this->expectException(ModelNotFoundException::class);
        $this->service()->findForShop($staff, $shop, $foreign->getKey());
    }

    public function test_customers_see_only_their_own_purchases(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $mine = Purchase::factory()->forBuyer($customer)->withLine()->create();
        Purchase::factory()->forBuyer($other)->withLine()->create();
        Purchase::factory()->withLine()->create();

        $page = $this->service()->listForCustomer($customer);

        $this->assertSame([$mine->getKey()], $page->pluck('purchase_id')->all());
        $this->assertSame($mine->getKey(), $this->service()->findForCustomer($customer, $mine->getKey())->getKey());

        $this->expectException(ModelNotFoundException::class);
        $this->service()->findForCustomer($other, $mine->getKey());
    }
}
