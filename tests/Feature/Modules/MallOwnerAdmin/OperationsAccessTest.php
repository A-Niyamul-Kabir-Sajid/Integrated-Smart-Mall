<?php

namespace Tests\Feature\Modules\MallOwnerAdmin;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AccountsAccess\Services\RoleAssignmentService;
use App\Modules\MallOwnerAdmin\Services\AnnouncementService;
use App\Modules\MallOwnerAdmin\Services\ComplaintService;
use App\Modules\MallOwnerAdmin\Services\WorkOrderService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ReviewsModeration\Models\Review;
use App\Modules\ReviewsModeration\Models\ReviewReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Mall-wide operations are for the mall's administrators only (Jira ISM-118): every route under
 * /admin carries an explicit access rule, no shop or customer account reaches them, and the
 * operational records and moderation decisions all land in the mall's activity log.
 */
class OperationsAccessTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private User $admin;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->admin = $this->administratorOf($this->mall);
        $this->shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
    }

    private function administratorOf(Mall $mall): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantMallRole($user, $mall, RoleCode::MallAdmin);

        return $user;
    }

    /**
     * @return list<RouteDefinition>
     */
    private function adminRoutes(): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'admin/')));
    }

    public function test_every_admin_route_requires_sign_in_and_an_administration_ability(): void
    {
        $this->assertGreaterThan(40, count($this->adminRoutes()));

        foreach ($this->adminRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Admin route [{$route->uri()}] must require sign-in.");
            $this->assertTrue(
                in_array('can:administer-mall,mall', $middleware, true) || in_array('can:administer-platform', $middleware, true),
                "Admin route [{$route->uri()}] must require administer-mall for its mall or administer-platform.",
            );
        }
    }

    public function test_every_mall_admin_page_is_closed_to_customers_shop_staff_and_other_malls_administrators(): void
    {
        $owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($owner, $this->shop, RoleCode::ShopOwner);
        $cashier = User::factory()->create(['email' => 'cashier@example.test']);
        app(MembershipService::class)->grantShopRole($cashier, $this->shop, RoleCode::ShopStaff);
        $customer = User::factory()->create();
        $otherAdmin = $this->administratorOf(Mall::factory()->create());

        $pages = collect($this->adminRoutes())
            ->filter(fn (RouteDefinition $route): bool => in_array('GET', $route->methods(), true) && $route->parameterNames() === ['mall'])
            ->map(fn (RouteDefinition $route): string => route($route->getName(), $this->mall))
            ->values();

        $this->assertGreaterThanOrEqual(10, $pages->count());

        foreach ($pages as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($pages as $url) {
            foreach ([$customer, $owner, $cashier, $otherAdmin] as $intruder) {
                $this->actingAs($intruder)->get($url)->assertForbidden();
            }
        }

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_the_platform_administrator_may_enter_every_mall_but_a_mall_administrator_only_their_own(): void
    {
        $platform = User::factory()->create();
        app(RoleAssignmentService::class)->assignPlatformRole($platform, RoleCode::PlatformAdmin);
        $otherMall = Mall::factory()->create();

        $this->actingAs($platform)->get(route('mall-admin.dashboard', $otherMall))->assertOk();
        $this->actingAs($platform)->get(route('mall-admin.complaints.index', $this->mall))->assertOk();
        $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $otherMall))->assertForbidden();
    }

    public function test_a_deactivated_administrator_is_signed_out_and_loses_access(): void
    {
        $this->admin->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $this->mall))->assertRedirect(route('login'));
    }

    public function test_the_dashboard_counts_what_is_waiting_in_every_queue(): void
    {
        $customer = User::factory()->create();
        $complaints = app(ComplaintService::class);
        $complaints->file($customer, $this->mall, null, ['category' => 'noise', 'subject' => 'Loud music', 'description' => 'The music near the atrium is far too loud.']);
        app(WorkOrderService::class)->create($this->admin, $this->mall, ['title' => 'Fix the door', 'description' => 'The main door sticks.', 'priority' => 'low']);

        $page = $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $this->mall));

        $page->assertOk()->assertSeeInOrder(['Complaints', '1'])->assertSeeInOrder(['Open work orders', '1']);
        $this->assertSame(1, $page->viewData('waiting')['complaints']);
        $this->assertSame(1, $page->viewData('waiting')['work_orders']);
        $this->assertSame(0, $page->viewData('waiting')['reported_reviews']);
    }

    public function test_reported_reviews_appear_in_the_dashboard_queue_and_moderation_is_audited_in_the_activity_log(): void
    {
        $review = Review::factory()->create(['shop_id' => $this->shop->getKey()]);
        ReviewReport::factory()->count(2)->create(['review_id' => $review->getKey()]);

        $page = $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $this->mall));
        $this->assertSame(1, $page->viewData('waiting')['reported_reviews']);
        $page->assertSee(route('moderation.index', $this->mall), false);

        $this->actingAs($this->admin)->get(route('moderation.index', $this->mall))->assertOk()->assertSee(route('mall-admin.dashboard', $this->mall), false);
        $this->actingAs($this->admin)->post(route('moderation.hide', [$this->mall, $review]), ['reason' => 'Contains personal details.'])->assertRedirect();

        $this->actingAs($this->admin)->get(route('mall-admin.activity.index', [$this->mall, 'area' => 'review']))->assertOk()->assertSee('review.moderated');
        $this->assertSame(0, $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $this->mall))->viewData('waiting')['reported_reviews']);
    }

    public function test_operational_records_are_written_to_the_activity_log_of_their_own_mall_only(): void
    {
        $customer = User::factory()->create();
        $otherMall = Mall::factory()->create();
        $otherAdmin = $this->administratorOf($otherMall);

        app(AnnouncementService::class)->create($this->admin, $this->mall, ['kind' => 'notice', 'title' => 'Lot B closed', 'body' => 'Lot B is closed on Friday.'], true);
        app(ComplaintService::class)->file($customer, $this->mall, null, ['category' => 'noise', 'subject' => 'Loud music', 'description' => 'The music near the atrium is far too loud.']);
        app(WorkOrderService::class)->create($this->admin, $this->mall, ['title' => 'Fix the door', 'description' => 'The main door sticks.', 'priority' => 'low']);
        app(AnnouncementService::class)->create($otherAdmin, $otherMall, ['kind' => 'notice', 'title' => 'Other mall secret', 'body' => 'Not for this mall.'], true);

        $page = $this->actingAs($this->admin)->get(route('mall-admin.activity.index', $this->mall));

        $page->assertOk()->assertSee('announcement.created')->assertSee('complaint.filed')->assertSee('work_order.created');
        $this->assertSame(3, $page->viewData('entries')->total() - $page->viewData('entries')->filter(fn ($entry) => str_starts_with($entry->action, 'access.'))->count());
    }
}
