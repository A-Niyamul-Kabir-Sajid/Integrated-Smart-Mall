<?php

namespace App\Modules\ChatbotRag\Console;

use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeSyncService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Console\Command;

/**
 * Captures the public records of every active mall (or one) as knowledge documents. Safe to run any
 * number of times: unchanged records capture nothing.
 */
class SyncKnowledge extends Command
{
    protected $signature = 'assistant:sync-knowledge {mall? : Slug of one mall; every active mall when left out}';

    protected $description = 'Refresh the assistant\'s knowledge of shops, product offers, floors and facilities';

    public function handle(KnowledgeSyncService $sync): int
    {
        $malls = Mall::query()->active()
            ->when(filled($this->argument('mall')), fn ($query) => $query->where('slug', $this->argument('mall')))
            ->orderBy('slug')
            ->get();

        if ($malls->isEmpty()) {
            $this->error('No active mall matches.');

            return self::FAILURE;
        }

        foreach ($malls as $mall) {
            $result = $sync->syncMall($mall);
            $this->info("{$mall->slug}: {$result['created']} created, {$result['updated']} updated, {$result['unchanged']} unchanged, {$result['withdrawn']} withdrawn.");
        }

        return self::SUCCESS;
    }
}
