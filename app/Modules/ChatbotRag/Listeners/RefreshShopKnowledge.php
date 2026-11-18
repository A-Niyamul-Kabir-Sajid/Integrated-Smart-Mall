<?php

namespace App\Modules\ChatbotRag\Listeners;

use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeSyncService;
use App\Modules\MallShopDirectory\Events\ShopStatusChanged;
use App\Modules\MallShopDirectory\Models\Mall;
use Throwable;

/**
 * When a shop is approved, suspended, reopened or archived, the assistant's knowledge of the mall
 * is refreshed at once, so a shop that stopped being public is no longer quoted. A failure here is
 * reported and never undoes or blocks the status change; the hourly sync repairs it.
 */
class RefreshShopKnowledge
{
    public function __construct(private KnowledgeSyncService $sync) {}

    public function handle(ShopStatusChanged $event): void
    {
        try {
            $mall = Mall::query()->find($event->mallId);

            if ($mall !== null) {
                $this->sync->syncMall($mall);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
