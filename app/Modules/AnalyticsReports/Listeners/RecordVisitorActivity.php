<?php

namespace App\Modules\AnalyticsReports\Listeners;

use App\Modules\AnalyticsReports\Services\ActivityRecorder;
use App\Modules\SharedPlatform\Events\VisitorActivity;

/**
 * Counts a visitor event while the request that caused it is still running.
 */
class RecordVisitorActivity
{
    public function __construct(private ActivityRecorder $recorder) {}

    public function handle(VisitorActivity $activity): void
    {
        $this->recorder->record($activity, request());
    }
}
