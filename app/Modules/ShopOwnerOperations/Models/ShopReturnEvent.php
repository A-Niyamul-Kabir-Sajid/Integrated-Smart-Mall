<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry of a return's history. Entries are append-only.
 */
#[Table(name: 'shop_return_events', key: 'shop_return_event_id', timestamps: false)]
#[Fillable(['shop_return_id', 'from_status', 'to_status', 'actor_user_id', 'note', 'created_at'])]
class ShopReturnEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Return history is final and cannot be changed.');
        });

        static::deleting(function (): never {
            throw new LogicException('Return history is final and cannot be deleted.');
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
