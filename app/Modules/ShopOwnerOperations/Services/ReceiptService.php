<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\PurchaseVerification\Models\Purchase;
use App\Modules\ShopOwnerOperations\Models\PurchaseReceipt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Issues the digital receipt of a recorded sale: one per sale, numbered like
 * RC-20261113-A7K2QX. Reading a receipt is authorised by the caller through the
 * purchase it belongs to (shop staff or the buyer).
 */
class ReceiptService
{
    public function issue(Purchase $purchase, ?User $issuer = null): PurchaseReceipt
    {
        $existing = PurchaseReceipt::query()->find($purchase->getKey());

        if ($existing !== null) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return PurchaseReceipt::query()->create([
                    'purchase_id' => $purchase->getKey(),
                    'shop_id' => $purchase->shop_id,
                    'receipt_number' => 'RC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'issued_by_user_id' => $issuer?->getKey(),
                    'issued_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                if (PurchaseReceipt::query()->whereKey($purchase->getKey())->exists()) {
                    return PurchaseReceipt::query()->findOrFail($purchase->getKey());
                }
            }
        }

        throw new \RuntimeException('Could not allocate a unique receipt number.');
    }

    public function forPurchase(Purchase $purchase): ?PurchaseReceipt
    {
        return PurchaseReceipt::query()->find($purchase->getKey());
    }
}
