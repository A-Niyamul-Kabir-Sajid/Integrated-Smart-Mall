<?php

namespace App\Modules\MallShopDirectory\Events;

use App\Modules\MallShopDirectory\Enums\ShopStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A shop moved through its lifecycle (approved, closed, reopened, archived).
 * Dispatched only after the change is committed.
 */
final class ShopStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $shopId,
        public readonly string $mallId,
        public readonly ShopStatus $from,
        public readonly ShopStatus $to,
    ) {}
}
