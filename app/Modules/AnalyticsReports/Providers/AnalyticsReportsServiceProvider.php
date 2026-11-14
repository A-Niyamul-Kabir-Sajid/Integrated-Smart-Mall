<?php

namespace App\Modules\AnalyticsReports\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\AnalyticsReports\Console\PurgeActivityEvents;
use App\Modules\AnalyticsReports\Console\RollupActivity;
use App\Modules\AnalyticsReports\Listeners\RecordVisitorActivity;
use App\Modules\SharedPlatform\Events\VisitorActivity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Registers AnalyticsReports: anonymous visitor activity, its daily summaries and the dashboards that
 * read them (Jira EPIC-14, decision D-016).
 */
class AnalyticsReportsServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [
        VisitorActivity::class => [RecordVisitorActivity::class],
    ];

    public function boot(): void
    {
        parent::boot();

        $this->commands([RollupActivity::class, PurgeActivityEvents::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(RollupActivity::class)->hourly()->onOneServer();
            $schedule->command(PurgeActivityEvents::class)->dailyAt('03:30')->onOneServer();
        });

        RateLimiter::for('analytics', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
