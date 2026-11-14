<?php

namespace App\Modules\AnalyticsReports\Services;

use App\Modules\MallShopDirectory\Models\Mall;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Deletes raw events older than the retention period. Before a day's events go, that day is rolled
 * up (so the summary is complete) and the summaries are never deleted.
 */
class ActivityRetentionService
{
    public function __construct(private ActivityRollupService $rollups) {}

    /**
     * @return array{search: int, view: int} raw events deleted
     */
    public function purge(?CarbonImmutable $now = null): array
    {
        $cutoff = ($now ?? CarbonImmutable::now())->subDays(max(1, (int) config('analytics.retention_days', 90)))->utc();
        $deleted = ['search' => 0, 'view' => 0];

        foreach (Mall::query()->get() as $mall) {
            $this->summariseExpiredDays($mall, $cutoff);
        }

        $deleted['search'] = DB::table('activity_search_events')->where('occurred_at', '<', $cutoff)->delete();
        $deleted['view'] = DB::table('activity_view_events')->where('occurred_at', '<', $cutoff)->delete();

        return $deleted;
    }

    private function summariseExpiredDays(Mall $mall, CarbonImmutable $cutoff): void
    {
        $earliest = collect([
            DB::table('activity_search_events')->where('mall_id', $mall->getKey())->where('occurred_at', '<', $cutoff)->min('occurred_at'),
            DB::table('activity_view_events')->where('mall_id', $mall->getKey())->where('occurred_at', '<', $cutoff)->min('occurred_at'),
        ])->filter()->map(fn ($moment): CarbonImmutable => CarbonImmutable::parse($moment, 'UTC'))->min();

        if ($earliest === null) {
            return;
        }

        $this->rollups->rollupRange($mall, $earliest, $cutoff->subSecond());
    }
}
