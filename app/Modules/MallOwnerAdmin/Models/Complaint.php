<?php

namespace App\Modules\MallOwnerAdmin\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallOwnerAdmin\Enums\ComplaintCategory;
use App\Modules\MallOwnerAdmin\Enums\ComplaintStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A customer's complaint to mall management (decision D-015). What was reported is fixed when
 * it is filed; the status, the assignee, the resolution note and the closing time change.
 */
#[Table(name: 'complaints', key: 'complaint_id')]
#[Fillable(['complaint_number', 'mall_id', 'customer_user_id', 'shop_id', 'category', 'subject', 'description', 'status', 'assigned_to_user_id', 'resolution_note', 'closed_at'])]
class Complaint extends Model
{
    use HasUuids;

    /** @var list<string> */
    private const FROZEN = ['complaint_number', 'mall_id', 'customer_user_id', 'shop_id', 'category', 'subject', 'description'];

    protected static function booted(): void
    {
        static::updating(function (Complaint $complaint): void {
            foreach (self::FROZEN as $attribute) {
                if ($complaint->isDirty($attribute)) {
                    throw new LogicException("Complaint {$attribute} cannot change after it is filed.");
                }
            }

            if ($complaint->isDirty('status')) {
                $from = ComplaintStatus::from($complaint->getRawOriginal('status'));

                if (! in_array($complaint->status, $from->allowedTransitions(), true)) {
                    throw new LogicException("A {$from->value} complaint cannot become {$complaint->status->value}.");
                }
            }
        });

        static::deleting(fn (): never => throw new LogicException('Complaints are never deleted; they are closed, rejected or withdrawn.'));
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
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
     * @return HasMany<ComplaintEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ComplaintEvent::class, 'complaint_id')->orderBy('complaint_event_id');
    }

    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'status' => ComplaintStatus::class,
            'closed_at' => 'immutable_datetime',
        ];
    }
}
