<?php

namespace App\Modules\ShopOwnerOperations\Console;

use App\Modules\ShopOwnerOperations\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Closes open orders that were not collected within the grace period after their
 * pickup time. Scheduled daily by the module provider.
 */
#[Signature('orders:expire-overdue')]
#[Description('Mark open shop orders that were not collected in time as expired')]
class ExpireOverdueOrders extends Command
{
    public function handle(OrderService $orders): int
    {
        $this->info(__(':count order(s) expired.', ['count' => $orders->expireOverdue()]));

        return self::SUCCESS;
    }
}
