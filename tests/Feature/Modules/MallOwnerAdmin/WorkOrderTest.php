<?php

namespace Tests\Feature\Modules\MallOwnerAdmin;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Enums\WorkOrderStatus;
use App\Modules\MallOwnerAdmin\Models\WorkOrder;
use App\Modules\MallOwnerAdmin\Models\WorkOrderEvent;
use App\Modules\MallOwnerAdmin\Services\WorkOrderService;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Models\AuditLog;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use App\Modules\ShopOwnerOperations\Services\MaintenanceRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    private User $admin;

    private User $technician;

    private Shop $shop;

    private User $owner;

    private WorkOrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->admin = $this->administratorOf($this->mall);
        $this->technician = $this->administratorOf($this->mall);
        $this->shop = Shop::factory()->create(['mall_id' => $this->mall->getKey()]);
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->orders = app(WorkOrderService::class);
    }

    private function administratorOf(Mall $mall): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantMallRole($user, $mall, RoleCode::MallAdmin);

        return $user;
    }

    private function request(): MaintenanceRequest
    {
        return app(MaintenanceRequestService::class)->create($this->owner, $this->shop, ['title' => 'Leaking tap', 'description' => 'The tap in the back room leaks.', 'category' => 'plumbing', 'priority' => 'urgent']);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(array $overrides = []): array
    {
        return ['title' => 'Replace corridor light', 'description' => 'The light outside the lift flickers.', 'priority' => 'normal', ...$overrides];
    }

    public function test_a_common_area_job_runs_from_creation_to_completion(): void
    {
        Notification::fake();
        $floor = Floor::factory()->create(['mall_id' => $this->mall->getKey()]);

        $order = $this->orders->create($this->admin, $this->mall, $this->data(['floor_id' => $floor->getKey(), 'location_note' => 'Next to lift 2', 'due_on' => '2030-01-15']));

        $this->assertMatchesRegularExpression('/^WO-[A-Z0-9]{8}$/', $order->work_order_number);
        $this->assertSame(WorkOrderStatus::Open, $order->status);
        $this->assertSame('2030-01-15', $order->due_on->format('Y-m-d'));

        $this->orders->assign($this->admin, $order, $this->technician);
        Notification::assertSentTo($this->technician, SystemNotice::class);
        $this->orders->start($this->technician, $order);
        $this->orders->complete($this->technician, $order, 'Replaced the tube.');

        $order->refresh();
        $this->assertSame(WorkOrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(['open', 'assigned', 'in_progress', 'completed'], $order->events->pluck('to_status')->all());
        $this->assertSame(4, AuditLog::query()->where('action', 'like', 'work_order.%')->where('mall_id', $this->mall->getKey())->count());
    }

    public function test_input_is_validated_and_the_assignee_must_be_an_administrator_of_the_mall(): void
    {
        foreach ([
            ['work_order.invalid_title', $this->data(['title' => 'ab'])],
            ['work_order.invalid_description', $this->data(['description' => 'abc'])],
            ['work_order.invalid_priority', $this->data(['priority' => 'whenever'])],
            ['work_order.floor_not_found', $this->data(['floor_id' => Floor::factory()->create()->getKey()])],
            ['work_order.invalid_due_date', $this->data(['due_on' => '15/01/2030'])],
        ] as [$rule, $data]) {
            try {
                $this->orders->create($this->admin, $this->mall, $data);
                $this->fail("Expected {$rule}.");
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame($rule, $violation->rule);
            }
        }

        $this->assertSame(0, WorkOrder::query()->count());

        $order = $this->orders->create($this->admin, $this->mall, $this->data());

        foreach ([$this->owner, User::factory()->create(), $this->administratorOf(Mall::factory()->create())] as $candidate) {
            try {
                $this->orders->assign($this->admin, $order, $candidate);
                $this->fail('Only administrators of the mall may be assigned.');
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('work_order.assignee_not_administrator', $violation->rule);
            }
        }
    }

    public function test_steps_cannot_be_skipped_and_cancelling_needs_a_reason(): void
    {
        $order = $this->orders->create($this->admin, $this->mall, $this->data());

        foreach ([
            fn () => $this->orders->start($this->admin, $order),
            fn () => $this->orders->complete($this->admin, $order, 'Done and dusted.'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A step must not be skipped.');
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('work_order.invalid_transition', $violation->rule);
            }
        }

        try {
            $this->orders->cancel($this->admin, $order, 'no');
            $this->fail('A cancel reason is required.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('work_order.reason_required', $violation->rule);
        }

        $this->orders->cancel($this->admin, $order, 'Duplicate of another job.');

        $this->expectException(BusinessRuleViolation::class);
        $this->orders->assign($this->admin, $order, $this->technician);
    }

    public function test_a_work_order_from_a_request_moves_the_shops_request_along(): void
    {
        Notification::fake();
        $request = $this->request();

        $order = $this->orders->create($this->admin, $this->mall, [], $request);

        $this->assertSame('Leaking tap', $order->title);
        $this->assertSame('urgent', $order->priority->value);
        $this->assertSame($this->shop->getKey(), $order->shop_id);
        $this->assertSame(MaintenanceStatus::Acknowledged, $request->refresh()->status);

        $this->orders->assign($this->admin, $order, $this->technician);
        $this->orders->start($this->technician, $order);
        $this->assertSame(MaintenanceStatus::InProgress, $request->refresh()->status);

        $this->orders->complete($this->technician, $order, 'Replaced the washer.');
        $request->refresh();
        $this->assertSame(MaintenanceStatus::Resolved, $request->status);
        $this->assertSame('Replaced the washer.', $request->resolution_note);
        Notification::assertSentTo($this->owner, SystemNotice::class);

        app(MaintenanceRequestService::class)->confirm($this->owner, $request);
        $this->assertSame(MaintenanceStatus::Closed, $request->refresh()->status);
    }

    public function test_a_request_has_one_open_work_order_at_a_time_and_a_cancelled_one_can_be_replaced(): void
    {
        $request = $this->request();
        $first = $this->orders->create($this->admin, $this->mall, [], $request);

        try {
            $this->orders->create($this->admin, $this->mall, [], $request);
            $this->fail('A second open work order must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('work_order.request_has_order', $violation->rule);
        }

        $this->orders->cancel($this->admin, $first, 'Wrong contractor booked.');
        $second = $this->orders->create($this->admin, $this->mall, [], $request);

        $this->assertNotSame($first->getKey(), $second->getKey());
        $this->assertSame($second->getKey(), $this->orders->openOrderOf($request)->getKey());
    }

    public function test_a_request_of_another_mall_or_one_that_is_finished_cannot_be_used(): void
    {
        $foreignOwner = User::factory()->create();
        $foreignShop = Shop::factory()->create();
        app(MembershipService::class)->grantShopRole($foreignOwner, $foreignShop, RoleCode::ShopOwner);
        $foreign = app(MaintenanceRequestService::class)->create($foreignOwner, $foreignShop, ['title' => 'Other mall tap', 'description' => 'It leaks as well.', 'category' => 'plumbing', 'priority' => 'low']);

        try {
            $this->orders->create($this->admin, $this->mall, [], $foreign);
            $this->fail('Another mall\'s request must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('work_order.request_in_other_mall', $violation->rule);
        }

        $cancelled = $this->request();
        app(MaintenanceRequestService::class)->cancel($this->owner, $cancelled);

        try {
            $this->orders->create($this->admin, $this->mall, [], $cancelled);
            $this->fail('A cancelled request must be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('work_order.request_not_open', $violation->rule);
        }

        $this->assertSame(0, WorkOrder::query()->count());
    }

    public function test_completing_after_the_shop_cancelled_does_not_break_the_work_order(): void
    {
        $request = $this->request();
        $order = $this->orders->create($this->admin, $this->mall, [], $request);
        $this->orders->assign($this->admin, $order, $this->technician);

        // The shop withdraws while the mall has only acknowledged it.
        app(MaintenanceRequestService::class)->cancel($this->owner, $request);
        $this->orders->start($this->technician, $order);
        $this->orders->complete($this->technician, $order, 'Fixed anyway.');

        $this->assertSame(WorkOrderStatus::Completed, $order->refresh()->status);
        $this->assertSame(MaintenanceStatus::Cancelled, $request->refresh()->status);
    }

    public function test_only_administrators_of_the_mall_may_work_its_orders(): void
    {
        $order = $this->orders->create($this->admin, $this->mall, $this->data());

        foreach ([$this->owner, User::factory()->create(), $this->administratorOf(Mall::factory()->create())] as $intruder) {
            try {
                $this->orders->assign($intruder, $order, $this->technician);
                $this->fail('Only administrators of the mall may assign.');
            } catch (AuthorizationException) {
                $this->assertSame(WorkOrderStatus::Open, $order->refresh()->status);
            }
        }
    }

    public function test_what_the_job_is_cannot_change_and_orders_and_history_are_never_deleted(): void
    {
        $order = $this->orders->create($this->admin, $this->mall, $this->data());

        foreach ([['title' => 'Changed'], ['priority' => 'urgent'], ['mall_id' => Mall::factory()->create()->getKey()]] as $change) {
            try {
                $order->update($change);
                $this->fail('A created work order is frozen.');
            } catch (LogicException) {
                $this->assertSame('Replace corridor light', $order->refresh()->title);
            }
        }

        foreach ([fn () => $order->delete(), fn () => WorkOrderEvent::query()->firstOrFail()->delete(), fn () => WorkOrderEvent::query()->firstOrFail()->update(['note' => 'x'])] as $attempt) {
            try {
                $attempt();
                $this->fail('Final records must not change.');
            } catch (LogicException) {
                $this->assertSame(1, WorkOrder::query()->count());
            }
        }
    }

    public function test_the_screens_create_assign_start_complete_and_keep_other_malls_out(): void
    {
        $otherMall = Mall::factory()->create();
        $otherAdmin = $this->administratorOf($otherMall);

        $this->actingAs($this->admin)->get(route('mall-admin.work-orders.create', $this->mall))->assertOk();
        $this->actingAs($this->admin)->post(route('mall-admin.work-orders.store', $this->mall), $this->data())->assertRedirect();
        $order = WorkOrder::query()->sole();

        $this->actingAs($this->admin)->get(route('mall-admin.work-orders.index', $this->mall))->assertOk()->assertSee('Replace corridor light');
        $this->actingAs($this->admin)->get(route('mall-admin.work-orders.show', [$this->mall, $order]))->assertOk()->assertSee($order->work_order_number);
        $this->actingAs($this->admin)->post(route('mall-admin.work-orders.assign', [$this->mall, $order]), ['assignee' => $this->technician->getKey(), 'due_on' => '2030-02-01'])->assertRedirect();
        $this->actingAs($this->technician)->get(route('mall-admin.work-orders.index', [$this->mall, 'mine' => 1]))->assertSee('Replace corridor light');
        $this->actingAs($this->technician)->post(route('mall-admin.work-orders.start', [$this->mall, $order]))->assertRedirect();
        $this->actingAs($this->technician)->post(route('mall-admin.work-orders.complete', [$this->mall, $order]), ['note' => ''])->assertSessionHasErrors('business');
        $this->actingAs($this->technician)->post(route('mall-admin.work-orders.complete', [$this->mall, $order]), ['note' => 'Tube replaced.'])->assertRedirect();
        $this->assertSame(WorkOrderStatus::Completed, $order->refresh()->status);

        $this->actingAs($this->admin)->post(route('mall-admin.work-orders.store', $this->mall), $this->data(['title' => '']))->assertSessionHasErrors('title');
        $this->actingAs($otherAdmin)->get(route('mall-admin.work-orders.show', [$otherMall, $order]))->assertNotFound();
        $this->actingAs($otherAdmin)->post(route('mall-admin.work-orders.cancel', [$otherMall, $order]), ['reason' => 'Sabotage attempt'])->assertNotFound();
        $this->actingAs($otherAdmin)->get(route('mall-admin.work-orders.index', $this->mall))->assertForbidden();
        $this->actingAs($this->owner)->get(route('mall-admin.work-orders.index', $this->mall))->assertForbidden();
        $this->actingAs($this->owner)->post(route('mall-admin.work-orders.store', $this->mall), $this->data())->assertForbidden();
        $this->assertSame(1, WorkOrder::query()->count());
    }

    public function test_the_mall_side_maintenance_screens_acknowledge_work_and_create_work_orders(): void
    {
        $request = $this->request();
        $otherMall = Mall::factory()->create();
        $otherAdmin = $this->administratorOf($otherMall);

        $this->actingAs($this->admin)->get(route('mall-admin.maintenance.index', $this->mall))->assertOk()->assertSee('Leaking tap')->assertSee($this->shop->name);
        $this->actingAs($this->admin)->get(route('mall-admin.maintenance.show', [$this->mall, $request]))->assertOk()->assertSee($request->request_number)->assertSee('Acknowledge');

        $this->actingAs($this->admin)->post(route('mall-admin.maintenance.acknowledge', [$this->mall, $request]))->assertRedirect();
        $this->assertSame(MaintenanceStatus::Acknowledged, $request->refresh()->status);

        $this->actingAs($this->admin)->post(route('mall-admin.maintenance.work-order', [$this->mall, $request]), ['location_note' => 'Back room', 'due_on' => '2030-03-01'])->assertRedirect();
        $order = WorkOrder::query()->sole();
        $this->assertSame($request->getKey(), $order->maintenance_request_id);
        $this->actingAs($this->admin)->get(route('mall-admin.maintenance.show', [$this->mall, $request]))->assertSee($order->work_order_number)->assertDontSee('Create a work order for this request');

        $this->actingAs($this->admin)->post(route('mall-admin.maintenance.start', [$this->mall, $request]))->assertRedirect();
        $this->actingAs($this->admin)->post(route('mall-admin.maintenance.resolve', [$this->mall, $request]), ['note' => ''])->assertSessionHasErrors('business');
        $this->actingAs($this->admin)->post(route('mall-admin.maintenance.resolve', [$this->mall, $request]), ['note' => 'Replaced the washer.'])->assertRedirect();
        $this->assertSame(MaintenanceStatus::Resolved, $request->refresh()->status);

        $this->actingAs($otherAdmin)->get(route('mall-admin.maintenance.show', [$otherMall, $request]))->assertNotFound();
        $this->actingAs($otherAdmin)->post(route('mall-admin.maintenance.acknowledge', [$otherMall, $request]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('mall-admin.maintenance.index', $this->mall))->assertForbidden();
        $this->actingAs($this->owner)->post(route('mall-admin.maintenance.resolve', [$this->mall, $request]), ['note' => 'I fixed it myself.'])->assertForbidden();
    }
}
