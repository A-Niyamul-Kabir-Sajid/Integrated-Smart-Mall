<?php

namespace App\Modules\MallOwnerAdmin\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallOwnerAdmin\Enums\WorkOrderStatus;
use App\Modules\MallOwnerAdmin\Models\WorkOrder;
use App\Modules\MallOwnerAdmin\Models\WorkOrderEvent;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\MaintenancePriority;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use App\Modules\ShopOwnerOperations\Services\MaintenanceRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Maintenance work orders of a mall (Jira ISM-116/ISM-117, decision D-015).
 *
 * An administrator creates a work order for a common-area job, or from a shop's maintenance
 * request, assigns it to an administrator of the mall, and follows it to completion. A work order
 * raised from a request moves the request along (acknowledged, being fixed, fixed) so the shop
 * sees progress; the shop still confirms the fix. Every step is kept in an append-only history.
 */
class WorkOrderService
{
    public function __construct(
        private MembershipService $memberships,
        private MaintenanceRequestService $requests,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{title?: string|null, description?: string|null, location_note?: string|null, floor_id?: string|null, priority?: string|null, due_on?: string|null}  $data
     */
    public function create(User $administrator, Mall $mall, array $data, ?MaintenanceRequest $request = null): WorkOrder
    {
        $this->authorize($administrator, $mall);

        if ($request !== null) {
            $this->assertRequestUsable($mall, $request);
        }

        $title = trim((string) ($data['title'] ?? $request?->title ?? ''));
        $description = trim((string) ($data['description'] ?? $request?->description ?? ''));

        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            throw new BusinessRuleViolation('work_order.invalid_title', __('Give the work order a title of 3 to 150 characters.'));
        }

        if (mb_strlen($description) < 5 || mb_strlen($description) > 2000) {
            throw new BusinessRuleViolation('work_order.invalid_description', __('Describe the job in 5 to 2000 characters.'));
        }

        $priority = MaintenancePriority::tryFrom((string) ($data['priority'] ?? $request?->priority->value ?? ''))
            ?? throw new BusinessRuleViolation('work_order.invalid_priority', __('Choose a priority.'));

        $floorId = filled($data['floor_id'] ?? null) ? $this->floorOf($mall, $data['floor_id']) : null;
        $locationNote = filled($data['location_note'] ?? null) ? mb_substr(trim($data['location_note']), 0, 160) : null;
        $dueOn = filled($data['due_on'] ?? null) ? $this->dueDate($data['due_on']) : null;

        $order = DB::transaction(function () use ($administrator, $mall, $request, $title, $description, $priority, $floorId, $locationNote, $dueOn): WorkOrder {
            $order = $this->createOrder([
                'mall_id' => $mall->getKey(),
                'maintenance_request_id' => $request?->getKey(),
                'shop_id' => $request?->shop_id,
                'floor_id' => $floorId,
                'title' => $title,
                'description' => $description,
                'location_note' => $locationNote,
                'priority' => $priority,
                'status' => WorkOrderStatus::Open,
                'due_on' => $dueOn,
                'created_by_user_id' => $administrator->getKey(),
            ]);

            $this->recordEvent($order, null, WorkOrderStatus::Open, $administrator, null);
            $this->audit->record('work_order.created', $order, ['number' => $order->work_order_number, 'priority' => $priority->value, 'request' => $request?->request_number], $mall->getKey(), $request?->shop_id);

            return $order;
        });

        if ($request !== null) {
            $this->syncRequest($administrator, $request, MaintenanceStatus::Acknowledged, __('Work order :number was created.', ['number' => $order->work_order_number]));
        }

        return $order;
    }

    /**
     * @param  array{status?: string|null, mine?: bool|null}  $filters
     * @return LengthAwarePaginator<int, WorkOrder>
     */
    public function listForMall(User $administrator, Mall $mall, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $this->authorize($administrator, $mall);

        $status = WorkOrderStatus::tryFrom((string) ($filters['status'] ?? ''));

        return WorkOrder::query()->where('mall_id', $mall->getKey())
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when(! empty($filters['mine']), fn ($query) => $query->where('assigned_to_user_id', $administrator->getKey()))
            ->with(['assignee:user_id,display_name', 'shop:shop_id,name', 'floor:floor_id,code,name'])
            ->orderByRaw("case when status in ('open', 'assigned', 'in_progress') then 0 else 1 end")
            ->orderByRaw("case priority when 'urgent' then 0 when 'normal' then 1 else 2 end")
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Gives the work order to an administrator of the mall (also used to reassign it).
     */
    public function assign(User $administrator, WorkOrder $order, User $assignee, ?string $dueOn = null): WorkOrder
    {
        $this->authorize($administrator, $order->loadMissing('mall')->mall);

        if (! $this->memberships->mallAdministrators($order->mall)->contains(fn (User $candidate): bool => $candidate->is($assignee))) {
            throw new BusinessRuleViolation('work_order.assignee_not_administrator', __('A work order can only be assigned to an administrator of this mall.'));
        }

        $extra = ['assigned_to_user_id' => $assignee->getKey()];

        if (filled($dueOn)) {
            $extra['due_on'] = $this->dueDate($dueOn);
        }

        $this->move($order, WorkOrderStatus::Assigned, $administrator, __('Assigned to :name', ['name' => $assignee->display_name]), $extra);

        if (! $assignee->is($administrator)) {
            $assignee->notify(new SystemNotice(__('Work order :number was assigned to you', ['number' => $order->work_order_number]), $order->title, route('mall-admin.work-orders.show', [$order->mall_id, $order])));
        }

        return $order;
    }

    public function start(User $administrator, WorkOrder $order): WorkOrder
    {
        $this->authorize($administrator, $order->loadMissing('mall')->mall);

        $this->move($order, WorkOrderStatus::InProgress, $administrator, null);

        if ($order->maintenance_request_id !== null) {
            $this->syncRequest($administrator, $order->loadMissing('maintenanceRequest')->maintenanceRequest, MaintenanceStatus::InProgress, null);
        }

        return $order;
    }

    public function complete(User $administrator, WorkOrder $order, string $note): WorkOrder
    {
        $this->authorize($administrator, $order->loadMissing('mall')->mall);

        $note = trim($note);

        if (mb_strlen($note) < 3 || mb_strlen($note) > 500) {
            throw new BusinessRuleViolation('work_order.note_required', __('Say what was done, in 3 to 500 characters.'));
        }

        $this->move($order, WorkOrderStatus::Completed, $administrator, $note, ['completion_note' => $note, 'completed_at' => now()]);

        if ($order->maintenance_request_id !== null) {
            $this->syncRequest($administrator, $order->loadMissing('maintenanceRequest')->maintenanceRequest, MaintenanceStatus::Resolved, $note);
        }

        return $order;
    }

    public function cancel(User $administrator, WorkOrder $order, string $reason): WorkOrder
    {
        $this->authorize($administrator, $order->loadMissing('mall')->mall);

        $reason = trim($reason);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new BusinessRuleViolation('work_order.reason_required', __('Write a reason of 5 to 500 characters.'));
        }

        return $this->move($order, WorkOrderStatus::Cancelled, $administrator, $reason);
    }

    /**
     * The open (not completed, not cancelled) work order of a request, if any.
     */
    public function openOrderOf(MaintenanceRequest $request): ?WorkOrder
    {
        return WorkOrder::query()->where('maintenance_request_id', $request->getKey())
            ->whereIn('status', array_map(fn (WorkOrderStatus $status): string => $status->value, WorkOrderStatus::openCases()))
            ->first();
    }

    private function assertRequestUsable(Mall $mall, MaintenanceRequest $request): void
    {
        if ($request->mall_id !== $mall->getKey()) {
            throw new BusinessRuleViolation('work_order.request_in_other_mall', __('This maintenance request belongs to another mall.'));
        }

        if (! in_array($request->status, [MaintenanceStatus::Open, MaintenanceStatus::Acknowledged, MaintenanceStatus::InProgress], true)) {
            throw new BusinessRuleViolation('work_order.request_not_open', __('A work order can only be created for a request that is still open.'));
        }

        if ($this->openOrderOf($request) !== null) {
            throw new BusinessRuleViolation('work_order.request_has_order', __('This request already has an open work order.'));
        }
    }

    /**
     * Moves the shop's request along when the work order progresses; skipped when the request has
     * meanwhile been cancelled or closed, or is already past that step.
     */
    private function syncRequest(User $administrator, ?MaintenanceRequest $request, MaintenanceStatus $to, ?string $note): void
    {
        if ($request === null) {
            return;
        }

        $current = MaintenanceRequest::query()->find($request->getKey())?->status;

        if ($current === null || ! in_array($to, $current->allowedTransitions(), true)) {
            return;
        }

        $this->requests->updateStatus($administrator, $request->refresh(), $to, $note);
    }

    private function floorOf(Mall $mall, string $floorId): string
    {
        return $mall->floors()->whereKey($floorId)->value('floor_id')
            ?? throw new BusinessRuleViolation('work_order.floor_not_found', __('Choose a floor of this mall.'));
    }

    private function dueDate(string $value): string
    {
        $date = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', $value), null, false);

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw new BusinessRuleViolation('work_order.invalid_due_date', __('Enter the due date as year-month-day.'));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOrder(array $attributes): WorkOrder
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                // A savepoint, so a clash on the number does not abort an enclosing PostgreSQL transaction.
                return DB::transaction(fn (): WorkOrder => WorkOrder::query()->create([...$attributes, 'work_order_number' => 'WO-'.Str::upper(Str::random(8))]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not allocate a unique work order number.');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function move(WorkOrder $order, WorkOrderStatus $to, User $actor, ?string $note, array $extra = []): WorkOrder
    {
        return DB::transaction(function () use ($order, $to, $actor, $note, $extra): WorkOrder {
            $locked = WorkOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $from = $locked->status;

            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new BusinessRuleViolation('work_order.invalid_transition', __('A :from work order cannot become :to.', ['from' => $from->value, 'to' => $to->value]));
            }

            $locked->update([...$extra, 'status' => $to]);
            $this->recordEvent($locked, $from, $to, $actor, $note);
            $this->audit->record('work_order.'.$to->value, $locked, array_filter(['number' => $locked->work_order_number, 'note' => $note]), $locked->mall_id, $locked->shop_id);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }

    private function recordEvent(WorkOrder $order, ?WorkOrderStatus $from, WorkOrderStatus $to, ?User $actor, ?string $note): void
    {
        WorkOrderEvent::query()->create([
            'work_order_id' => $order->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->getKey(),
            'note' => $note === null ? null : mb_substr($note, 0, 1000),
            'created_at' => now(),
        ]);
    }

    private function authorize(User $administrator, Mall $mall): void
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);
    }
}
