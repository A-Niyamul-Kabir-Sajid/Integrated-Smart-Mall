<?php

namespace Tests\Feature\Modules\MallOwnerAdmin;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Services\TenantAdministrationService;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\SharedPlatform\Models\AuditLog;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\ShopOwnerOperations\Enums\OrderStatus;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TenantAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private User $admin;

    private ShopCategory $category;

    private TenantAdministrationService $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->category = ShopCategory::factory()->create();
        $this->admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($this->admin, $this->mall, RoleCode::MallAdmin);
        $this->tenants = app(TenantAdministrationService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function shopData(array $overrides = []): array
    {
        return ['slug' => 'corner-books', 'name' => 'Corner Books', 'description' => 'Books.', 'contact_phone' => '01712345678', ...$overrides];
    }

    public function test_an_administrator_creates_a_draft_shop_with_a_unit_and_an_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email' => 'tenant@example.test']);
        $floor = Floor::factory()->create(['mall_id' => $this->mall->getKey()]);

        $shop = $this->tenants->create($this->admin, $this->mall, $this->category, $this->shopData([
            'floor_id' => $floor->getKey(),
            'unit_code' => 'g-07',
            'area_sq_m' => '42.5',
            'owner_contact' => 'Tenant@Example.test',
        ]));

        $this->assertSame(ShopStatus::Draft, $shop->status);
        $this->assertSame($this->mall->getKey(), $shop->mall_id);
        $this->assertSame('G-07', $shop->units()->sole()->unit_code);
        $this->assertTrue($owner->hasShopRole($shop->getKey(), RoleCode::ShopOwner));
        $this->assertTrue(AuditLog::query()->where('action', 'shop.created')->where('shop_id', $shop->getKey())->exists());
        Notification::assertSentTo($owner, SystemNotice::class);
    }

    public function test_creating_a_shop_needs_an_existing_owner_account_and_a_unit_code_with_a_floor(): void
    {
        $floor = Floor::factory()->create(['mall_id' => $this->mall->getKey()]);

        try {
            $this->tenants->create($this->admin, $this->mall, $this->category, $this->shopData(['owner_contact' => 'nobody@example.test']));
            $this->fail('An unknown owner must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('tenant.owner_not_found', $violation->rule);
        }

        try {
            $this->tenants->create($this->admin, $this->mall, $this->category, $this->shopData(['floor_id' => $floor->getKey()]));
            $this->fail('A floor without a unit code must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('tenant.unit_code_required', $violation->rule);
        }

        $this->assertSame(0, Shop::query()->count());
    }

    public function test_a_failed_unit_assignment_leaves_no_half_created_shop(): void
    {
        $floor = Floor::factory()->create(['mall_id' => $this->mall->getKey()]);
        ShopUnit::factory()->create(['shop_id' => Shop::factory()->create(['mall_id' => $this->mall->getKey()])->getKey(), 'floor_id' => $floor->getKey(), 'unit_code' => 'G-01']);
        $before = Shop::query()->count();

        $this->expectException(BusinessRuleViolation::class);

        try {
            $this->tenants->create($this->admin, $this->mall, $this->category, $this->shopData(['floor_id' => $floor->getKey(), 'unit_code' => 'G-01']));
        } finally {
            $this->assertSame($before, Shop::query()->count());
        }
    }

    public function test_a_floor_of_another_mall_is_refused(): void
    {
        $foreignFloor = Floor::factory()->create();

        try {
            $this->tenants->create($this->admin, $this->mall, $this->category, $this->shopData(['floor_id' => $foreignFloor->getKey(), 'unit_code' => 'A-1']));
            $this->fail('Another mall\'s floor must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('tenant.floor_not_found', $violation->rule);
        }
    }

    public function test_update_changes_the_profile_and_category_and_is_audited(): void
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Old Name']);
        $other = ShopCategory::factory()->create();

        $this->tenants->update($this->admin, $this->mall, $shop, $other, ['name' => 'New Name', 'description' => 'Fresh', 'contact_phone' => '01712345678']);

        $shop->refresh();
        $this->assertSame('New Name', $shop->name);
        $this->assertSame($other->getKey(), $shop->shop_category_id);
        $this->assertTrue(AuditLog::query()->where('action', 'shop.category_changed')->where('shop_id', $shop->getKey())->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'shop.profile_changed')->where('shop_id', $shop->getKey())->exists());
    }

    public function test_an_archived_shop_cannot_be_edited(): void
    {
        $shop = Shop::factory()->archived()->create(['mall_id' => $this->mall->getKey()]);

        $this->expectException(BusinessRuleViolation::class);

        $this->tenants->update($this->admin, $this->mall, $shop, $this->category, ['name' => 'Anything']);
    }

    public function test_activation_needs_a_draft_shop_with_an_owner(): void
    {
        Notification::fake();
        $shop = Shop::factory()->draft()->create(['mall_id' => $this->mall->getKey()]);

        try {
            $this->tenants->activate($this->admin, $this->mall, $shop);
            $this->fail('A shop without an owner must not be activated.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('tenant.owner_required', $violation->rule);
        }

        $owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($owner, $shop, RoleCode::ShopOwner);

        $this->tenants->activate($this->admin, $this->mall, $shop);

        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);
        Notification::assertSentTo($owner, SystemNotice::class);

        $this->expectException(BusinessRuleViolation::class);
        $this->tenants->activate($this->admin, $this->mall, $shop);
    }

    public function test_archiving_needs_a_reason_and_no_open_orders_then_releases_the_units(): void
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $unit = ShopUnit::factory()->create(['shop_id' => $shop->getKey(), 'floor_id' => Floor::factory()->create(['mall_id' => $this->mall->getKey()])->getKey()]);

        try {
            $this->tenants->archive($this->admin, $this->mall, $shop, 'no');
            $this->fail('A short reason must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('tenant.reason_required', $violation->rule);
        }

        $order = ShopOrder::factory()->create(['shop_id' => $shop->getKey(), 'status' => OrderStatus::Accepted]);

        try {
            $this->tenants->archive($this->admin, $this->mall, $shop, 'Lease ended');
            $this->fail('A shop with open orders must not be archived.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('tenant.open_orders', $violation->rule);
        }

        $order->update(['status' => OrderStatus::Cancelled]);
        $this->tenants->archive($this->admin, $this->mall, $shop, 'Lease ended');

        $this->assertSame(ShopStatus::Archived, $shop->refresh()->status);
        $this->assertSame('archived', $unit->refresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'shop.status_changed')->where('shop_id', $shop->getKey())->where('metadata->to', 'archived')->exists());
    }

    public function test_a_released_unit_code_can_be_taken_by_the_next_tenant(): void
    {
        $first = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $second = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $floor = Floor::factory()->create(['mall_id' => $this->mall->getKey()]);

        $unit = $this->tenants->assignUnit($this->admin, $this->mall, $first, $floor->getKey(), 'G-09');

        try {
            $this->tenants->assignUnit($this->admin, $this->mall, $second, $floor->getKey(), 'G-09');
            $this->fail('An occupied unit must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('shop_unit.code_taken', $violation->rule);
        }

        $this->tenants->releaseUnit($this->admin, $this->mall, $first, $unit);
        $taken = $this->tenants->assignUnit($this->admin, $this->mall, $second, $floor->getKey(), 'G-09', 30);

        $this->assertSame($unit->getKey(), $taken->getKey());
        $this->assertSame($second->getKey(), $taken->shop_id);
        $this->assertSame('open', $taken->status);
        $this->assertSame(1, $floor->shopUnits()->count());
    }

    public function test_owners_can_be_added_and_the_last_owner_of_an_active_shop_cannot_be_removed(): void
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $first = User::factory()->create(['email' => 'first@example.test']);
        $second = User::factory()->create(['email' => 'second@example.test']);

        $this->tenants->addOwner($this->admin, $this->mall, $shop, 'first@example.test');

        try {
            $this->tenants->removeOwner($this->admin, $this->mall, $shop, $first);
            $this->fail('The last owner of an active shop must stay.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('tenant.last_owner', $violation->rule);
        }

        $this->tenants->addOwner($this->admin, $this->mall, $shop, 'second@example.test');
        $this->tenants->removeOwner($this->admin, $this->mall, $shop, $first);

        $this->assertFalse($first->hasShopRole($shop->getKey(), RoleCode::ShopOwner));
        $this->assertTrue($second->hasShopRole($shop->getKey(), RoleCode::ShopOwner));
    }

    public function test_an_administrator_of_another_mall_and_a_shop_owner_are_refused(): void
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $otherAdmin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($otherAdmin, Mall::factory()->create(), RoleCode::MallAdmin);
        $owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($owner, $shop, RoleCode::ShopOwner);

        foreach ([$otherAdmin, $owner] as $intruder) {
            try {
                $this->tenants->update($intruder, $this->mall, $shop, $this->category, ['name' => 'Hijacked']);
                $this->fail('Only administrators of the mall may change its shops.');
            } catch (AuthorizationException) {
                $this->assertSame($shop->name, $shop->refresh()->name);
            }
        }
    }

    public function test_a_shop_of_another_mall_is_refused_even_for_this_malls_administrator(): void
    {
        $foreign = Shop::factory()->create();

        $this->expectException(BusinessRuleViolation::class);

        $this->tenants->update($this->admin, $this->mall, $foreign, $this->category, ['name' => 'Hijacked']);
    }
}
