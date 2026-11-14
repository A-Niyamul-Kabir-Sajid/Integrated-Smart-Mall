<?php

namespace Tests\Feature\Modules\MallOwnerAdmin;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AccountsAccess\Services\RoleAssignmentService;
use App\Modules\MallShopDirectory\Enums\FacilityType;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MallAdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($this->admin, $this->mall, RoleCode::MallAdmin);
    }

    /**
     * @return list<string>
     */
    private function guardedUrls(Shop $shop): array
    {
        return [
            route('mall-admin.dashboard', $this->mall),
            route('mall-admin.profile.edit', $this->mall),
            route('mall-admin.floors.index', $this->mall),
            route('mall-admin.shops.index', $this->mall),
            route('mall-admin.shops.create', $this->mall),
            route('mall-admin.activity.index', $this->mall),
            route('mall-admin.shops.edit', [$this->mall, $shop]),
        ];
    }

    public function test_every_screen_is_closed_to_guests_customers_shop_staff_and_other_malls_administrators(): void
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($owner, $shop, RoleCode::ShopOwner);
        $otherAdmin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($otherAdmin, Mall::factory()->create(), RoleCode::MallAdmin);

        foreach ($this->guardedUrls($shop) as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($this->guardedUrls($shop) as $url) {
            foreach ([User::factory()->create(), $owner, $otherAdmin] as $intruder) {
                $this->actingAs($intruder)->get($url)->assertForbidden();
            }
        }

        $this->actingAs($owner)->post(route('mall-admin.shops.store', $this->mall), ['name' => 'X'])->assertForbidden();
        $this->actingAs($owner)->post(route('mall-admin.shops.activate', [$this->mall, $shop]))->assertForbidden();
        $this->actingAs($owner)->put(route('mall-admin.profile.update', $this->mall), ['name' => 'Hijacked'])->assertForbidden();
        $this->assertNotSame('Hijacked', $this->mall->refresh()->name);
    }

    public function test_the_mall_administrator_reaches_every_screen(): void
    {
        $shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);

        foreach ($this->guardedUrls($shop) as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_the_dashboard_shows_the_queues_and_the_tenant_counts(): void
    {
        Shop::factory()->count(2)->create(['mall_id' => $this->mall->getKey()]);
        Shop::factory()->draft()->create(['mall_id' => $this->mall->getKey()]);

        $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $this->mall))
            ->assertOk()
            ->assertSee('Shop applications')
            ->assertSee('Reported reviews')
            ->assertSee('Active shops');
    }

    public function test_the_account_page_links_a_mall_administrator_to_the_dashboard(): void
    {
        $this->actingAs($this->admin)->get(route('account.show'))->assertOk()->assertSee(route('mall-admin.dashboard', $this->mall), false);
    }

    public function test_a_shop_is_created_edited_activated_and_archived_through_the_screens(): void
    {
        $category = ShopCategory::factory()->create();
        $floor = Floor::factory()->create(['mall_id' => $this->mall->getKey()]);
        $owner = User::factory()->create(['email' => 'tenant@example.test']);

        $this->actingAs($this->admin)->post(route('mall-admin.shops.store', $this->mall), [
            'name' => 'Corner Books',
            'slug' => 'corner-books',
            'shop_category_id' => $category->getKey(),
            'floor_id' => $floor->getKey(),
            'unit_code' => 'G-01',
            'owner_contact' => 'tenant@example.test',
        ])->assertRedirect();

        $shop = Shop::query()->where('slug', 'corner-books')->sole();
        $this->assertSame(ShopStatus::Draft, $shop->status);

        $this->actingAs($this->admin)->get(route('mall-admin.shops.edit', [$this->mall, $shop]))->assertOk()->assertSee('G-01')->assertSee('tenant@example.test');
        $this->actingAs($this->admin)->put(route('mall-admin.shops.update', [$this->mall, $shop]), ['name' => 'Corner Books Ltd', 'shop_category_id' => $category->getKey()])->assertRedirect();
        $this->actingAs($this->admin)->post(route('mall-admin.shops.activate', [$this->mall, $shop]))->assertRedirect();
        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);
        $this->assertSame('Corner Books Ltd', $shop->name);

        $this->actingAs($this->admin)->post(route('mall-admin.shops.archive', [$this->mall, $shop]), ['reason' => 'Lease ended'])->assertRedirect(route('mall-admin.shops.index', $this->mall));
        $this->assertSame(ShopStatus::Archived, $shop->refresh()->status);
        $this->assertSame('archived', ShopUnit::query()->where('shop_id', $shop->getKey())->sole()->status);
        $this->assertNotNull($owner);
    }

    public function test_validation_errors_and_rule_violations_save_nothing(): void
    {
        $category = ShopCategory::factory()->create();

        $this->actingAs($this->admin)->post(route('mall-admin.shops.store', $this->mall), ['name' => '', 'slug' => 'x', 'shop_category_id' => $category->getKey()])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('mall-admin.shops.store', $this->mall), ['name' => 'Bad', 'slug' => 'Bad Slug!', 'shop_category_id' => $category->getKey()])->assertSessionHasErrors('business');

        $this->assertSame(0, Shop::query()->count());
    }

    public function test_a_shop_of_another_mall_is_not_found_through_this_malls_urls(): void
    {
        $foreign = Shop::factory()->create();

        $this->actingAs($this->admin)->get(route('mall-admin.shops.edit', [$this->mall, $foreign]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('mall-admin.shops.activate', [$this->mall, $foreign]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('mall-admin.shops.archive', [$this->mall, $foreign]), ['reason' => 'Because'])->assertNotFound();
        $this->assertNotSame(ShopStatus::Archived, $foreign->refresh()->status);
    }

    public function test_the_shop_list_filters_by_status_and_name_and_pages(): void
    {
        Shop::factory()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Alpha Cafe']);
        Shop::factory()->draft()->create(['mall_id' => $this->mall->getKey(), 'name' => 'Beta Books']);
        Shop::factory()->create(['name' => 'Gamma Elsewhere']);

        $this->actingAs($this->admin)->get(route('mall-admin.shops.index', $this->mall))->assertSee('Alpha Cafe')->assertSee('Beta Books')->assertDontSee('Gamma Elsewhere');
        $this->actingAs($this->admin)->get(route('mall-admin.shops.index', [$this->mall, 'status' => 'draft']))->assertSee('Beta Books')->assertDontSee('Alpha Cafe');
        $this->actingAs($this->admin)->get(route('mall-admin.shops.index', [$this->mall, 'q' => 'alpha']))->assertSee('Alpha Cafe')->assertDontSee('Beta Books');
        $this->actingAs($this->admin)->get(route('mall-admin.shops.index', [$this->mall, 'status' => 'nonsense']))->assertSessionHasErrors('status');
    }

    public function test_the_profile_is_edited_and_the_mall_opened_and_archived(): void
    {
        $draft = Mall::factory()->draft()->create();
        app(MembershipService::class)->grantMallRole($this->admin, $draft, RoleCode::MallAdmin);

        $this->actingAs($this->admin)->put(route('mall-admin.profile.update', $draft), ['name' => 'Riverside Mall', 'address' => '1 River Road', 'city' => 'Khulna', 'timezone' => 'Asia/Dhaka'])->assertRedirect();
        $this->assertSame('Riverside Mall', $draft->refresh()->name);

        $this->actingAs($this->admin)->put(route('mall-admin.profile.update', $draft), ['name' => 'Riverside Mall', 'address' => '1 River Road', 'city' => 'Khulna', 'timezone' => 'Mars/Olympus'])->assertSessionHasErrors('business');

        $this->actingAs($this->admin)->post(route('mall-admin.profile.status', $draft), ['status' => 'active'])->assertRedirect();
        $this->assertSame(MallStatus::Active, $draft->refresh()->status);
        $this->actingAs($this->admin)->post(route('mall-admin.profile.status', $draft), ['status' => 'draft'])->assertSessionHasErrors('business');
        $this->assertTrue(AuditLog::query()->where('action', 'mall.profile_changed')->where('mall_id', $draft->getKey())->exists());
    }

    public function test_floors_are_added_renamed_archived_and_restored(): void
    {
        $this->actingAs($this->admin)->post(route('mall-admin.floors.store', $this->mall), ['code' => 'f2', 'name' => 'Second floor', 'sort_order' => 2, 'elevation_m' => 9])->assertRedirect();
        $floor = $this->mall->floors()->where('code', 'F2')->sole();

        $this->actingAs($this->admin)->post(route('mall-admin.floors.store', $this->mall), ['code' => 'F2', 'name' => 'Duplicate', 'sort_order' => 3, 'elevation_m' => 12])->assertSessionHasErrors('business');
        $this->assertSame(1, $this->mall->floors()->where('code', 'F2')->count());

        $this->actingAs($this->admin)->put(route('mall-admin.floors.update', [$this->mall, $floor]), ['name' => 'Food court level', 'sort_order' => 5, 'elevation_m' => 10.5])->assertRedirect();
        $this->assertSame('Food court level', $floor->refresh()->name);
        $this->assertSame('F2', $floor->code);

        $this->actingAs($this->admin)->post(route('mall-admin.floors.archive', [$this->mall, $floor]))->assertRedirect();
        $this->assertSame('archived', $floor->refresh()->status);

        $this->actingAs($this->admin)->post(route('mall-admin.floors.restore', [$this->mall, $floor]))->assertRedirect();
        $this->assertSame('active', $floor->refresh()->status);
        $this->assertSame(3, AuditLog::query()->whereIn('action', ['floor.created', 'floor.updated', 'floor.archived', 'floor.restored'])->where('mall_id', $this->mall->getKey())->count() - 1);
    }

    public function test_a_floor_with_units_or_facilities_cannot_be_archived(): void
    {
        $floor = Floor::factory()->create(['mall_id' => $this->mall->getKey()]);
        $unit = ShopUnit::factory()->create(['shop_id' => Shop::factory()->create(['mall_id' => $this->mall->getKey()])->getKey(), 'floor_id' => $floor->getKey()]);

        $this->actingAs($this->admin)->post(route('mall-admin.floors.archive', [$this->mall, $floor]))->assertSessionHasErrors('business');
        $this->assertSame('active', $floor->refresh()->status);

        $unit->update(['status' => 'archived']);
        Facility::factory()->create(['floor_id' => $floor->getKey(), 'facility_type' => FacilityType::Toilet, 'status' => 'open']);

        $this->actingAs($this->admin)->post(route('mall-admin.floors.archive', [$this->mall, $floor]))->assertSessionHasErrors('business');
        $this->assertSame('active', $floor->refresh()->status);
    }

    public function test_a_floor_of_another_mall_is_not_found(): void
    {
        $foreign = Floor::factory()->create();

        $this->actingAs($this->admin)->post(route('mall-admin.floors.archive', [$this->mall, $foreign]))->assertNotFound();
        $this->actingAs($this->admin)->put(route('mall-admin.floors.update', [$this->mall, $foreign]), ['name' => 'X', 'sort_order' => 1, 'elevation_m' => 0])->assertNotFound();
        $this->assertSame('active', $foreign->refresh()->status);
    }

    public function test_categories_are_platform_administration_only(): void
    {
        $platform = User::factory()->create();
        app(RoleAssignmentService::class)->assignPlatformRole($platform, RoleCode::PlatformAdmin);

        $this->actingAs($this->admin)->get(route('mall-admin.categories.index'))->assertForbidden();
        $this->actingAs($this->admin)->post(route('mall-admin.categories.store'), ['name' => 'Toys', 'slug' => 'toys'])->assertForbidden();
        $this->assertSame(0, ShopCategory::query()->count());

        $this->actingAs($platform)->get(route('mall-admin.categories.index'))->assertOk();
        $this->actingAs($platform)->post(route('mall-admin.categories.store'), ['name' => 'Toys', 'slug' => 'toys'])->assertRedirect();
        $toys = ShopCategory::query()->where('slug', 'toys')->sole();

        $this->actingAs($platform)->put(route('mall-admin.categories.update', $toys), ['name' => 'Toys and games'])->assertRedirect();
        $this->assertSame('Toys and games', $toys->refresh()->name);

        $this->actingAs($platform)->put(route('mall-admin.categories.update', $toys), ['name' => 'Toys', 'parent_category_id' => $toys->getKey()])->assertSessionHasErrors('business');
        $this->assertNull($toys->refresh()->parent_category_id);
    }

    public function test_the_activity_log_shows_only_this_malls_records_and_filters_by_area(): void
    {
        $category = ShopCategory::factory()->create();
        $this->actingAs($this->admin)->post(route('mall-admin.shops.store', $this->mall), ['name' => 'Corner Books', 'slug' => 'corner-books', 'shop_category_id' => $category->getKey()])->assertRedirect();
        $this->actingAs($this->admin)->post(route('mall-admin.floors.store', $this->mall), ['code' => 'F9', 'name' => 'Roof', 'sort_order' => 9, 'elevation_m' => 30])->assertRedirect();

        $otherMall = Mall::factory()->create();
        $otherAdmin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($otherAdmin, $otherMall, RoleCode::MallAdmin);
        $this->actingAs($otherAdmin)->post(route('mall-admin.floors.store', $otherMall), ['code' => 'SECRET', 'name' => 'Other mall', 'sort_order' => 1, 'elevation_m' => 0])->assertRedirect();

        $this->flushSession();
        $page = $this->actingAs($this->admin)->get(route('mall-admin.activity.index', $this->mall));
        $page->assertOk()->assertSee('shop.created')->assertSee('floor.created')->assertSee($this->admin->display_name)->assertDontSee('SECRET');

        $this->actingAs($this->admin)->get(route('mall-admin.activity.index', [$this->mall, 'area' => 'floor']))
            ->assertOk()->assertSee('floor.created')->assertDontSee('shop.created');

        $this->actingAs($this->admin)->get(route('mall-admin.activity.index', [$this->mall, 'area' => 'x; drop']))->assertSessionHasErrors('area');
    }
}
