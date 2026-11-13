<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ShopOwnerOperations\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of the stock ledger. Entries are never changed or deleted: a mistake is
 * corrected by a later entry (decision D-014).
 */
#[Table(name: 'stock_movements', key: 'stock_movement_id', timestamps: false)]
#[Fillable(['shop_product_id', 'shop_id', 'movement_type', 'quantity_delta', 'balance_after', 'reason', 'reference_type', 'reference_id', 'actor_user_id', 'created_at'])]
class StockMovement extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A stock movement is final and cannot be changed.');
        });

        static::deleting(function (): never {
            throw new LogicException('A stock movement is final and cannot be deleted.');
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
            'movement_type' => StockMovementType::class,
            'quantity_delta' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
