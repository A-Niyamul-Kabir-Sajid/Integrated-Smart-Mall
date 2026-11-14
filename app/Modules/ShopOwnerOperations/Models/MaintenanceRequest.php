<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceCategory;
use App\Modules\ShopOwnerOperations\Enums\MaintenancePriority;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A shop's request that mall management fixes something (decision D-014). What was asked is
 * fixed when it is raised; the status, the resolution note and the closing time change.
 */
#[Table(name: 'maintenance_requests', key: 'maintenance_request_id')]
#[Fillable(['request_number', 'shop_id', 'mall_id', 'requested_by_user_id', 'title', 'description', 'category', 'priority', 'status', 'resolution_note', 'closed_at'])]
class MaintenanceRequest extends Model
{
    use HasUuids;

    /** @var list<string> */
    private const FROZEN = ['request_number', 'shop_id', 'mall_id', 'requested_by_user_id', 'title', 'description', 'category', 'priority'];

    protected static function booted(): void
    {
        static::updating(function (MaintenanceRequest $request): void {
            foreach (self::FROZEN as $attribute) {
                if ($request->isDirty($attribute)) {
                    throw new LogicException("Maintenance request {$attribute} cannot change after it is raised.");
                }
            }

            if ($request->isDirty('status')) {
                $from = MaintenanceStatus::from($request->getRawOriginal('status'));

                if (! in_array($request->status, $from->allowedTransitions(), true)) {
                    throw new LogicException("A {$from->value} request cannot become {$request->status->value}.");
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Maintenance requests are never deleted; cancel them instead.');
        });
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return HasMany<MaintenanceRequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(MaintenanceRequestEvent::class, 'maintenance_request_id')->orderBy('maintenance_request_event_id');
    }

    protected function casts(): array
    {
        return [
            'category' => MaintenanceCategory::class,
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceStatus::class,
            'closed_at' => 'immutable_datetime',
        ];
    }
}
