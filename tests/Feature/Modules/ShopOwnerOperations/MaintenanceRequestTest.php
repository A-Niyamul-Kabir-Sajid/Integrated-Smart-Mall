<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Services\MaintenanceRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class MaintenanceRequestTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private Shop $shop;

    private User $owner;

    private User $mallAdmin;

    private MaintenanceRequestService $requests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->shop = Shop::factory()->for($this->mall)->create(['name' => 'Cafe Brew']);
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->mallAdmin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($this->mallAdmin, $this->mall, RoleCode::MallAdmin);
        $this->requests = app(MaintenanceRequestService::class);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function raise(array $overrides = []): MaintenanceRequest
    {
        return $this->requests->create($this->owner, $this->shop, ['title' => 'Leaking tap', 'description' => 'The tap in the back room leaks.', 'category' => 'plumbing', 'priority' => 'urgent', ...$overrides]);
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

    public function test_the_owner_raises_a_request_and_the_mall_is_told(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->get(route('workspace.maintenance.index', $this->shop))->assertOk()->assertSee('No requests here yet.');
        $this->post(route('workspace.maintenance.store', $this->shop), ['title' => 'Leaking tap', 'description' => 'The tap leaks badly.', 'category' => 'plumbing', 'priority' => 'urgent'])->assertRedirect();

        $request = MaintenanceRequest::query()->sole();
        $this->assertMatchesRegularExpression('/^MNT-[A-Z0-9]{8}$/', $request->request_number);
        $this->assertSame($this->mall->getKey(), $request->mall_id);
        $this->assertSame(MaintenanceStatus::Open, $request->status);
        Notification::assertSentTo($this->mallAdmin, SystemNotice::class);
        $this->get(route('workspace.maintenance.show', [$this->shop, $request]))->assertOk()->assertSee('Leaking tap')->assertSee('Cancel this request');
        $this->get(route('workspace.maintenance.index', $this->shop))->assertSee($request->request_number);
    }

    public function test_invalid_requests_are_refused(): void
    {
        $this->assertSame('maintenance.invalid_category', $this->rule(fn () => $this->raise(['category' => 'magic'])));
        $this->assertSame('maintenance.invalid_priority', $this->rule(fn () => $this->raise(['priority' => 'asap'])));
        $this->assertSame('maintenance.invalid_title', $this->rule(fn () => $this->raise(['title' => 'x'])));
        $this->assertSame('maintenance.invalid_description', $this->rule(fn () => $this->raise(['description' => 'no'])));

        $this->actingAs($this->owner)->post(route('workspace.maintenance.store', $this->shop), ['title' => '', 'description' => '', 'category' => 'x', 'priority' => 'y'])->assertSessionHasErrors(['title', 'description', 'category', 'priority']);
        $this->assertSame(0, MaintenanceRequest::query()->count());

        foreach (range(1, MaintenanceRequestService::MAX_OPEN_PER_SHOP) as $i) {
            $this->raise(['title' => "Problem $i"]);
        }

        $this->assertSame('maintenance.too_many_open', $this->rule(fn () => $this->raise()));
    }

    public function test_the_mall_works_the_request_and_the_shop_confirms(): void
    {
        $request = $this->raise();
        Notification::fake();

        $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::Acknowledged);
        $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::InProgress, 'Plumber booked for 3 pm');
        $this->assertSame('maintenance.note_required', $this->rule(fn () => $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::Resolved)));
        $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::Resolved, 'Replaced the washer');
        Notification::assertSentTo($this->owner, SystemNotice::class, 3);

        $this->actingAs($this->owner)->get(route('workspace.maintenance.show', [$this->shop, $request]))->assertSee('Fixed, please confirm')->assertSee('Replaced the washer');
        $this->post(route('workspace.maintenance.confirm', [$this->shop, $request]))->assertRedirect();

        $request->refresh();
        $this->assertSame(MaintenanceStatus::Closed, $request->status);
        $this->assertNotNull($request->closed_at);
        $this->assertSame(['open', 'acknowledged', 'in_progress', 'resolved', 'closed'], $request->events->pluck('to_status')->all());
    }

    public function test_the_shop_can_reopen_a_fix_that_did_not_work_and_cancel_before_work_starts(): void
    {
        $request = $this->raise();
        $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::Acknowledged);
        $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::InProgress);
        $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::Resolved, 'Tightened the tap');

        $this->assertSame('maintenance.note_required', $this->rule(fn () => $this->requests->reopen($this->owner, $request, ' ')));
        $this->actingAs($this->owner)->post(route('workspace.maintenance.reopen', [$this->shop, $request]), ['note' => 'Still dripping'])->assertRedirect();
        $this->assertSame(MaintenanceStatus::InProgress, $request->fresh()->status);

        $second = $this->raise(['title' => 'Broken light']);
        $this->requests->cancel($this->owner, $second);
        $this->assertSame(MaintenanceStatus::Cancelled, $second->fresh()->status);

        $started = $this->raise(['title' => 'Cold air conditioner']);
        $this->requests->updateStatus($this->mallAdmin, $started, MaintenanceStatus::Acknowledged);
        $this->requests->updateStatus($this->mallAdmin, $started, MaintenanceStatus::InProgress);
        $this->assertSame('maintenance.invalid_transition', $this->rule(fn () => $this->requests->cancel($this->owner, $started)));
    }

    public function test_invalid_transitions_and_mall_only_statuses_are_refused(): void
    {
        $request = $this->raise();

        $this->assertSame('maintenance.invalid_transition', $this->rule(fn () => $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::Resolved, 'skipping ahead')));
        $this->assertSame('maintenance.invalid_transition', $this->rule(fn () => $this->requests->confirm($this->owner, $request)));
        $this->assertSame('maintenance.mall_cannot_set', $this->rule(fn () => $this->requests->updateStatus($this->mallAdmin, $request, MaintenanceStatus::Closed)));
        $this->assertSame(MaintenanceStatus::Open, $request->fresh()->status);

        $this->expectException(LogicException::class);
        $request->update(['title' => 'Rewritten history']);
    }

    public function test_only_the_right_people_may_touch_a_request(): void
    {
        $request = $this->raise();
        $cashier = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($cashier, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::Cashier]);
        $otherAdmin = User::factory()->create();
        app(MembershipService::class)->grantMallRole($otherAdmin, Mall::factory()->create(), RoleCode::MallAdmin);
        $otherOwner = User::factory()->create();
        $otherShop = Shop::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);

        foreach ([$this->owner, $cashier, $otherOwner] as $user) {
            try {
                $this->requests->updateStatus($user, $request, MaintenanceStatus::Acknowledged);
                $this->fail('Only a mall administrator of this mall may move the request.');
            } catch (AuthorizationException) {
                $this->assertSame(MaintenanceStatus::Open, $request->fresh()->status);
            }
        }

        $this->expectException(AuthorizationException::class);
        $this->requests->updateStatus($otherAdmin, $request, MaintenanceStatus::Acknowledged);
    }

    public function test_workspace_access_and_isolation(): void
    {
        $request = $this->raise();
        $cashier = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($cashier, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::Cashier]);
        $manager = User::factory()->create();
        $managerMembership = app(MembershipService::class)->grantShopRole($manager, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $managerMembership->getKey(), 'staff_function' => ShopFunction::Manager]);
        $otherOwner = User::factory()->create();
        $otherShop = Shop::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);

        $this->get(route('workspace.maintenance.index', $this->shop))->assertRedirect(route('login'));

        foreach ([$cashier, User::factory()->create(), $otherOwner] as $user) {
            $this->actingAs($user)->get(route('workspace.maintenance.index', $this->shop))->assertForbidden();
            $this->post(route('workspace.maintenance.store', $this->shop), ['title' => 'Hacked', 'description' => 'nope nope', 'category' => 'other', 'priority' => 'low'])->assertForbidden();
        }

        $this->actingAs($manager)->get(route('workspace.maintenance.index', $this->shop))->assertOk()->assertSee('Leaking tap');
        $this->actingAs($otherOwner)->get(route('workspace.maintenance.show', [$otherShop, $request]))->assertNotFound();
        $this->post(route('workspace.maintenance.cancel', [$otherShop, $request]))->assertNotFound();
        $this->assertSame(1, MaintenanceRequest::query()->count());
    }

    public function test_the_mall_lists_requests_of_its_shops_urgent_first(): void
    {
        $this->raise(['title' => 'Dusty sign', 'priority' => 'low']);
        $this->raise(['title' => 'Flooding', 'priority' => 'urgent']);
        $foreignShop = Shop::factory()->create();
        $foreignOwner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($foreignOwner, $foreignShop, RoleCode::ShopOwner);
        $this->requests->create($foreignOwner, $foreignShop, ['title' => 'Other mall problem', 'description' => 'something wrong', 'category' => 'other', 'priority' => 'urgent']);

        $titles = $this->requests->listForMall($this->mallAdmin, $this->mall)->pluck('title')->all();

        $this->assertSame(['Flooding', 'Dusty sign'], $titles);
        $this->assertCount(2, $this->requests->listForMall($this->mallAdmin, $this->mall, MaintenanceStatus::Open));
        $this->assertCount(0, $this->requests->listForMall($this->mallAdmin, $this->mall, MaintenanceStatus::Closed));

        $this->expectException(AuthorizationException::class);
        $this->requests->listForMall($this->owner, $this->mall);
    }
}
