<?php

namespace App\Modules\AnalyticsReports\Console;

use App\Modules\AnalyticsReports\Services\ActivityRollupService;
use App\Modules\MallShopDirectory\Models\Mall;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Recomputes the daily activity summaries of every mall for its most recent days (mall time zone).
 * Safe to run any number of times: a day is recomputed from the raw events.
 */
class RollupActivity extends Command
{
    protected $signature = 'analytics:rollup {--days=2 : How many of the latest days to recompute} {--date= : One day as year-month-day instead}';

    protected $description = 'Summarise visitor activity events into daily figures';

    public function handle(ActivityRollupService $rollups): int
    {
        $rows = 0;

        foreach (Mall::query()->get() as $mall) {
            $today = CarbonImmutable::now($mall->timezone)->startOfDay();

            if (filled($this->option('date'))) {
                $day = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'), $mall->timezone);

                if ($day === false) {
                    $this->error('Enter the date as year-month-day.');

                    return self::FAILURE;
                }

                $rows += $rollups->rollupDay($mall, $day);

                continue;
            }

            $rows += $rollups->rollupRange($mall, $today->subDays(max(1, (int) $this->option('days')) - 1), $today);
        }

        $this->info("Wrote {$rows} summary rows.");

        return self::SUCCESS;
    }
}
