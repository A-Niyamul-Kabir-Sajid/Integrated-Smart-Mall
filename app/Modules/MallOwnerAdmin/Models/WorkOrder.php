<?php

namespace App\Modules\MallOwnerAdmin\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallOwnerAdmin\Enums\WorkOrderStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\MaintenancePriority;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A maintenance job the mall's administrators assign and track (decision D-015), either raised
 * from a shop's maintenance request or created by the mall itself for a common area. What the job
 * is stays fixed; the status, the assignee, the due date and the completion note change.
 */
#[Table(name: 'work_orders', key: 'work_order_id')]
#[Fillable(['work_order_number', 'mall_id', 'maintenance_request_id', 'floor_id', 'shop_id', 'title', 'description', 'location_note', 'priority', 'status', 'assigned_to_user_id', 'due_on', 'completion_note', 'created_by_user_id', 'completed_at'])]
class WorkOrder extends Model
{
    use HasUuids;

    /** @var list<string> */
    private const FROZEN = ['work_order_number', 'mall_id', 'maintenance_request_id', 'floor_id', 'shop_id', 'title', 'description', 'location_note', 'priority', 'created_by_user_id'];

    protected static function booted(): void
    {
        static::updating(function (WorkOrder $order): void {
            foreach (self::FROZEN as $attribute) {
                if ($order->isDirty($attribute)) {
                    throw new LogicException("Work order {$attribute} cannot change after it is created.");
                }
            }

            if ($order->isDirty('status')) {
                $from = WorkOrderStatus::from($order->getRawOriginal('status'));

                if (! in_array($order->status, $from->allowedTransitions(), true)) {
                    throw new LogicException("A {$from->value} work order cannot become {$order->status->value}.");
                }
            }
        });

        static::deleting(fn (): never => throw new LogicException('Work orders are never deleted; cancel them instead.'));
    }

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<MaintenanceRequest, $this>
     */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    /**
     * @return BelongsTo<Floor, $this>
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return HasMany<WorkOrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class, 'work_order_id')->orderBy('work_order_event_id');
    }

    protected function casts(): array
    {
        return [
            'priority' => MaintenancePriority::class,
            'status' => WorkOrderStatus::class,
            'due_on' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
