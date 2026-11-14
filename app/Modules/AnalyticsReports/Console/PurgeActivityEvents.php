<?php

namespace App\Modules\AnalyticsReports\Console;

use App\Modules\AnalyticsReports\Services\ActivityRetentionService;
use Illuminate\Console\Command;

/**
 * Deletes raw search and view events older than the retention period, after summarising their days.
 */
class PurgeActivityEvents extends Command
{
    protected $signature = 'analytics:purge-events';

    protected $description = 'Delete raw visitor events past the retention period (daily summaries are kept)';

    public function handle(ActivityRetentionService $retention): int
    {
        $deleted = $retention->purge();

        $this->info("Deleted {$deleted['search']} search events and {$deleted['view']} view events.");

        return self::SUCCESS;
    }
}
