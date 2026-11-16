<?php

namespace App\Modules\ChatbotRag\Console;

use App\Modules\ChatbotRag\Services\Assistant\ConversationService;
use App\Modules\ChatbotRag\Services\Assistant\InteractionLog;
use Illuminate\Console\Command;

/**
 * Deletes what the assistant may no longer keep: conversations past their retention time, with
 * their messages, citations and recommendations, and interaction records older than the configured
 * number of days. Safe to run any number of times.
 */
class PurgeAssistantData extends Command
{
    protected $signature = 'assistant:purge';

    protected $description = 'Delete expired assistant conversations and old interaction records';

    public function handle(ConversationService $conversations, InteractionLog $log): int
    {
        $this->info("Deleted {$conversations->purgeExpired()} expired conversations and {$log->purgeOld()} old interaction records.");

        return self::SUCCESS;
    }
}
