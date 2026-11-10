<?php

namespace App\Modules\PurchaseVerification\Events;

use App\Modules\PurchaseVerification\Enums\PurchaseStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A recorded sale was voided or refunded. Modules that rely on the sale as evidence
 * (for example verified reviews) react to it. Dispatched only after the change is committed.
 */
final class PurchaseStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $purchaseId,
        public readonly string $shopId,
        public readonly PurchaseStatus $from,
        public readonly PurchaseStatus $to,
    ) {}
}
