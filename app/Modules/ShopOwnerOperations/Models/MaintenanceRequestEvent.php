<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry of a maintenance request's history. Entries are append-only.
 */
#[Table(name: 'maintenance_request_events', key: 'maintenance_request_event_id', timestamps: false)]
#[Fillable(['maintenance_request_id', 'from_status', 'to_status', 'actor_user_id', 'note', 'created_at'])]
class MaintenanceRequestEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Maintenance history is final and cannot be changed.');
        });

        static::deleting(function (): never {
            throw new LogicException('Maintenance history is final and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
