<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\PurchaseVerification\Models\Purchase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The digital receipt of one recorded sale. One receipt per sale, never changed.
 */
#[Table(name: 'purchase_receipts', key: 'purchase_id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['purchase_id', 'shop_id', 'receipt_number', 'issued_by_user_id', 'issued_at'])]
class PurchaseReceipt extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A receipt is final and cannot be changed.');
        });

        static::deleting(function (): never {
            throw new LogicException('A receipt is final and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
        ];
    }
}
