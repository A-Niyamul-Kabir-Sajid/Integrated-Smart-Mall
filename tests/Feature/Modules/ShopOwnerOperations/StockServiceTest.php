<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\SharedPlatform\Models\AuditLog;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Enums\StockMovementType;
use App\Modules\ShopOwnerOperations\Models\ListingStock;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Models\StockMovement;
use App\Modules\ShopOwnerOperations\Services\StockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private ShopProduct $listing;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->listing = ShopProduct::factory()->for($this->shop)->create(['availability_status' => 'unknown']);
        $this->stock = app(StockService::class);
    }

    private function staffWith(ShopFunction $function): User
    {
        $user = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($user, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => $function]);

        return $user;
    }

    public function test_deliveries_counts_sales_and_returns_keep_quantity_equal_to_the_ledger(): void
    {
        $this->stock->receive($this->owner, $this->listing, 20, 'Supplier invoice 7');
        $this->stock->recordSale($this->listing, 5, 'purchase', 'p-1', $this->owner);
        $this->stock->adjustTo($this->owner, $this->listing, 12, 'Two bottles broke');
        $this->stock->recordReturn($this->listing, 1, 'return', 'r-1', $this->owner);
        $this->stock->receive($this->owner, $this->listing, 8);

        $row = ListingStock::query()->sole();
        $this->assertSame(21, $row->on_hand);
        $this->assertSame(21, (int) StockMovement::query()->sum('quantity_delta'));
        $this->assertSame([20, 15, 12, 13, 21], StockMovement::query()->orderBy('stock_movement_id')->pluck('balance_after')->all());
        $this->assertSame([StockMovementType::Receipt, StockMovementType::Sale, StockMovementType::Adjustment, StockMovementType::Return, StockMovementType::Receipt], StockMovement::query()->orderBy('stock_movement_id')->get()->map->movement_type->all());
        $this->assertCount(0, $this->stock->reconcile($this->shop));
        $this->assertTrue(AuditLog::query()->where('action', 'stock.receipt')->exists());
    }

    public function test_reconciliation_reports_a_quantity_that_drifted_from_the_ledger(): void
    {
        $this->stock->receive($this->owner, $this->listing, 10);
        ListingStock::query()->update(['on_hand' => 99]);

        $mismatches = $this->stock->reconcile($this->shop);

        $this->assertCount(1, $mismatches);
        $this->assertSame(['shop_product_id' => $this->listing->getKey(), 'on_hand' => 99, 'ledger_total' => 10], $mismatches->first());
    }

    public function test_stock_never_goes_negative_and_a_sale_cannot_exceed_it(): void
    {
        $this->stock->receive($this->owner, $this->listing, 3);

        try {
            $this->stock->recordSale($this->listing, 4, 'purchase', 'p-1');
            $this->fail('A sale above the stock must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('stock.insufficient', $violation->rule);
        }

        $this->assertSame(3, ListingStock::query()->sole()->on_hand);
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertFalse($this->stock->canSupply($this->listing, 4));
        $this->assertTrue($this->stock->canSupply($this->listing, 3));
    }

    public function test_input_rules_are_enforced(): void
    {
        foreach ([[0], [-2], [StockService::MAX_QUANTITY + 1]] as [$quantity]) {
            try {
                $this->stock->receive($this->owner, $this->listing, $quantity);
                $this->fail("Quantity $quantity must be refused.");
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('stock.invalid_quantity', $violation->rule);
            }
        }

        $this->stock->receive($this->owner, $this->listing, 5);

        $this->expectException(BusinessRuleViolation::class);
        $this->stock->adjustTo($this->owner, $this->listing, 5, 'same count');
    }

    public function test_an_adjustment_needs_a_reason_and_a_valid_count(): void
    {
        $this->stock->receive($this->owner, $this->listing, 5);

        foreach ([['', 3, 'stock.reason_required'], ['  ', 3, 'stock.reason_required'], ['Recount', -1, 'stock.invalid_quantity'], ['Recount', 5, 'stock.no_change']] as [$reason, $count, $rule]) {
            try {
                $this->stock->adjustTo($this->owner, $this->listing, $count, $reason);
                $this->fail("Expected $rule.");
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame($rule, $violation->rule);
            }
        }

        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_the_availability_flag_follows_the_quantity(): void
    {
        $this->stock->receive($this->owner, $this->listing, 2);
        $this->assertSame('available', $this->listing->refresh()->availability_status);

        $this->stock->recordSale($this->listing, 2, 'purchase', 'p-1');
        $this->assertSame('unavailable', $this->listing->refresh()->availability_status);

        $this->stock->receive($this->owner, $this->listing, 1);
        $this->assertSame('available', $this->listing->refresh()->availability_status);
    }

    public function test_untracked_listings_ignore_sale_and_return_movements(): void
    {
        $this->assertNull($this->stock->recordSale($this->listing, 2, 'purchase', 'p-1'));
        $this->assertNull($this->stock->recordReturn($this->listing, 1, 'return', 'r-1'));
        $this->assertTrue($this->stock->canSupply($this->listing, 500));
        $this->assertSame(0, ListingStock::query()->count());
        $this->assertSame('unknown', $this->listing->refresh()->availability_status);
    }

    public function test_the_owner_is_alerted_once_when_stock_falls_to_the_threshold(): void
    {
        $this->stock->receive($this->owner, $this->listing, 10);
        $this->stock->setThreshold($this->owner, $this->listing, 4);
        Notification::fake();

        $this->stock->recordSale($this->listing, 3, 'purchase', 'p-1');
        Notification::assertNothingSent();

        $this->stock->recordSale($this->listing, 3, 'purchase', 'p-2');
        Notification::assertSentToTimes($this->owner, SystemNotice::class, 1);

        $this->stock->recordSale($this->listing, 1, 'purchase', 'p-3');
        Notification::assertSentToTimes($this->owner, SystemNotice::class, 1);
        $this->assertTrue(ListingStock::query()->sole()->isLow());
        $this->assertSame(1, $this->stock->lowStockCount($this->shop));

        $this->stock->setThreshold($this->owner, $this->listing, 0);
        $this->assertFalse(ListingStock::query()->sole()->isLow());
        $this->assertSame(0, $this->stock->lowStockCount($this->shop));
    }

    public function test_the_ledger_cannot_be_edited_or_deleted(): void
    {
        $this->stock->receive($this->owner, $this->listing, 3);
        $movement = StockMovement::query()->sole();

        try {
            $movement->update(['quantity_delta' => 99]);
            $this->fail('Ledger rows must be immutable.');
        } catch (LogicException) {
            $this->assertSame(3, $movement->refresh()->quantity_delta);
        }

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_only_the_stock_functions_of_this_shop_may_change_stock(): void
    {
        $this->stock->receive($this->staffWith(ShopFunction::CatalogueEditor), $this->listing, 1);
        $this->stock->receive($this->staffWith(ShopFunction::Manager), $this->listing, 1);

        $outsiders = [
            'cashier' => $this->staffWith(ShopFunction::Cashier),
            'no role' => User::factory()->create(),
            'other shop owner' => tap(User::factory()->create(), fn (User $user) => app(MembershipService::class)->grantShopRole($user, Shop::factory()->create(), RoleCode::ShopOwner)),
        ];

        foreach ($outsiders as $label => $user) {
            try {
                $this->stock->receive($user, $this->listing, 5);
                $this->fail("$label must be refused.");
            } catch (AuthorizationException) {
                $this->assertSame(2, (int) StockMovement::query()->sum('quantity_delta'), $label);
            }
        }
    }

    public function test_stock_is_unavailable_while_the_shop_is_not_active(): void
    {
        $this->shop->update(['status' => 'closed']);

        $this->expectException(AuthorizationException::class);
        $this->stock->receive($this->owner, $this->listing->refresh(), 5);
    }
}
