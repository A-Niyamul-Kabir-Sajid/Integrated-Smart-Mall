<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\SharedPlatform\Models\AuditLog;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\ShopOwnerOperations\Enums\ApplicationStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Models\ShopApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShopApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private User $admin;

    private User $applicant;

    private ShopCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->category = ShopCategory::factory()->create();
        $this->admin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($this->admin, $this->mall, RoleCode::MallAdmin);
        $this->applicant = User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'mall_id' => $this->mall->getKey(),
            'shop_category_id' => $this->category->getKey(),
            'name' => 'Corner Books',
            'description' => 'Books and stationery.',
            'contact_phone' => '01712345678',
            'message' => 'We would like a ground floor unit.',
            ...$overrides,
        ];
    }

    private function apply(array $overrides = []): ShopApplication
    {
        $before = ShopApplication::query()->pluck('shop_application_id')->all();

        $this->actingAs($this->applicant)->post(route('shop-applications.store'), $this->form($overrides))->assertRedirect(route('shop-applications.index'));

        return ShopApplication::query()->whereNotIn('shop_application_id', $before)->sole();
    }

    public function test_application_pages_require_sign_in(): void
    {
        $this->get(route('shop-applications.index'))->assertRedirect(route('login'));
        $this->get(route('shop-applications.create'))->assertRedirect(route('login'));
        $this->post(route('shop-applications.store'), $this->form())->assertRedirect(route('login'));
    }

    public function test_applying_creates_a_draft_shop_an_owner_membership_and_a_pending_application(): void
    {
        Notification::fake();

        $this->actingAs($this->applicant)->get(route('shop-applications.create'))->assertOk()->assertSee($this->mall->name);
        $application = $this->apply();

        $shop = $application->shop;
        $this->assertSame(ShopStatus::Draft, $shop->status);
        $this->assertSame('Corner Books', $shop->name);
        $this->assertSame($this->mall->getKey(), $shop->mall_id);
        $this->assertSame(ApplicationStatus::Pending, $application->status);
        $this->assertTrue($this->applicant->hasShopRole($shop->getKey(), RoleCode::ShopOwner));
        $this->assertTrue(AuditLog::query()->where('action', 'shop_application.submitted')->exists());
        Notification::assertSentTo($this->admin, SystemNotice::class);

        $this->get(route('shop-applications.index'))->assertSee('Corner Books')->assertSee('Waiting for a decision');
        $this->get(route('shops.show', ['publicMall' => $this->mall->slug, 'publicShop' => $shop->slug]))->assertNotFound();
    }

    public function test_two_applications_with_the_same_name_get_distinct_addresses(): void
    {
        $first = $this->apply();
        $second = $this->apply();

        $this->assertNotSame($first->shop->slug, $second->shop->slug);
    }

    public function test_an_applicant_may_have_only_three_pending_applications(): void
    {
        foreach (range(1, 3) as $i) {
            $this->apply(['name' => "Shop $i"]);
        }

        $this->actingAs($this->applicant)->post(route('shop-applications.store'), $this->form(['name' => 'Shop 4']))->assertSessionHasErrors('business');
        $this->assertSame(3, ShopApplication::query()->count());
    }

    public function test_invalid_applications_are_refused(): void
    {
        $this->actingAs($this->applicant);

        $this->post(route('shop-applications.store'), $this->form(['name' => '']))->assertSessionHasErrors('name');
        $this->post(route('shop-applications.store'), $this->form(['mall_id' => 'nope']))->assertSessionHasErrors('mall_id');
        $this->post(route('shop-applications.store'), $this->form(['contact_phone' => 'abc']))->assertSessionHasErrors('business');

        $archived = Mall::factory()->create(['status' => 'archived']);
        $this->post(route('shop-applications.store'), $this->form(['mall_id' => $archived->getKey()]))->assertNotFound();

        $this->assertSame(0, ShopApplication::query()->count());
        $this->assertSame(0, Shop::query()->count());
    }

    public function test_the_mall_administrator_approves_and_the_shop_becomes_operational(): void
    {
        $application = $this->apply();
        $shop = $application->shop;
        $this->assertFalse(Gate::forUser($this->applicant)->allows(ShopArea::Stock->ability(), $shop));

        Notification::fake();
        $this->actingAs($this->admin)->get(route('shop-applications.admin.index', $this->mall))->assertOk()->assertSee('Corner Books');
        $this->post(route('shop-applications.admin.approve', [$this->mall, $application]))->assertRedirect();

        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);
        $this->assertSame(ApplicationStatus::Approved, $application->refresh()->status);
        $this->assertSame($this->admin->getKey(), $application->decided_by_user_id);
        $this->assertTrue(Gate::forUser($this->applicant)->allows(ShopArea::Stock->ability(), $shop));
        Notification::assertSentTo($this->applicant, SystemNotice::class);

        $this->post(route('shop-applications.admin.approve', [$this->mall, $application]))->assertSessionHasErrors('business');
    }

    public function test_rejecting_needs_a_reason_and_archives_the_draft_shop(): void
    {
        $application = $this->apply();
        $this->actingAs($this->admin);

        $this->post(route('shop-applications.admin.reject', [$this->mall, $application]), ['reason' => 'no'])->assertSessionHasErrors('business');
        $this->assertSame(ApplicationStatus::Pending, $application->refresh()->status);

        $this->post(route('shop-applications.admin.reject', [$this->mall, $application]), ['reason' => 'The unit is already let.'])->assertRedirect();

        $this->assertSame(ApplicationStatus::Rejected, $application->refresh()->status);
        $this->assertSame(ShopStatus::Archived, $application->shop->refresh()->status);
        $this->actingAs($this->applicant)->get(route('shop-applications.index'))->assertSee('The unit is already let.');
    }

    public function test_only_administrators_of_the_same_mall_may_decide(): void
    {
        $application = $this->apply();

        $this->actingAs($this->applicant)->post(route('shop-applications.admin.approve', [$this->mall, $application]))->assertForbidden();
        $this->get(route('shop-applications.admin.index', $this->mall))->assertForbidden();

        $otherMall = Mall::factory()->create();
        $otherAdmin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($otherAdmin, $otherMall, RoleCode::MallAdmin);

        $this->actingAs($otherAdmin)->post(route('shop-applications.admin.approve', [$this->mall, $application]))->assertForbidden();
        $this->post(route('shop-applications.admin.approve', [$otherMall, $application]))->assertNotFound();
        $this->assertSame(ApplicationStatus::Pending, $application->refresh()->status);
    }

    public function test_suspending_closes_the_shop_for_operations_and_only_the_mall_reopens_it(): void
    {
        $shop = Shop::factory()->for($this->mall)->create();
        $owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($owner, $shop, RoleCode::ShopOwner);
        $this->assertTrue(Gate::forUser($owner)->allows(ShopArea::Orders->ability(), $shop));

        $this->actingAs($this->admin)->post(route('shop-applications.admin.suspend', [$this->mall, $shop]), ['reason' => 'x'])->assertSessionHasErrors('business');
        $this->post(route('shop-applications.admin.suspend', [$this->mall, $shop]), ['reason' => 'Unpaid service charge.'])->assertRedirect();

        $this->assertSame(ShopStatus::Closed, $shop->refresh()->status);
        $this->assertFalse(Gate::forUser($owner)->allows(ShopArea::Orders->ability(), $shop));
        $this->actingAs($owner)->get(route('workspace.dashboard', $shop))->assertOk()->assertSee('The mall suspended this shop');
        $this->post(route('shop-applications.admin.reactivate', [$this->mall, $shop]))->assertForbidden();

        $this->actingAs($this->admin)->post(route('shop-applications.admin.reactivate', [$this->mall, $shop]))->assertRedirect();
        $this->assertSame(ShopStatus::Active, $shop->refresh()->status);
        $this->assertTrue(Gate::forUser($owner)->allows(ShopArea::Orders->ability(), $shop));
        $this->assertTrue(AuditLog::query()->where('action', 'shop.status_changed')->where('shop_id', $shop->getKey())->exists());
    }

    public function test_a_shop_of_another_mall_cannot_be_suspended_through_this_mall(): void
    {
        $foreign = Shop::factory()->create();

        $this->actingAs($this->admin)->post(route('shop-applications.admin.suspend', [$this->mall, $foreign]), ['reason' => 'Because I can.'])->assertNotFound();
        $this->assertSame(ShopStatus::Active, $foreign->refresh()->status);
    }
}
