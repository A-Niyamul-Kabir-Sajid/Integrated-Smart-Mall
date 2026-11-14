<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Models\MonthlyReport;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Generates and stores monthly reports (Jira EPIC-12, decision D-017). A report is the dashboard of the
 * calendar month in the mall's time zone, the dashboard of the month before for comparison, and a narrative
 * drafted from those figures. It is a snapshot: later changes to the source records do not rewrite it, and
 * generating the same month again replaces it. A month that has not ended is reported up to today and marked
 * partial.
 */
class MonthlyReportService
{
    public function __construct(
        private readonly ShopDashboardService $shopDashboards,
        private readonly MallDashboardService $mallDashboards,
        private readonly NarrativeBuilder $narrative,
        private readonly AuditLogger $audit,
    ) {}

    public function generateForShop(User $owner, Shop $shop, string $month): MonthlyReport
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);

        $mall = $shop->loadMissing('mall')->mall;
        [$period, $previous, $partial] = $this->periods($mall, $month);

        $payload = $this->payload('shop', ['id' => (string) $shop->getKey(), 'name' => $shop->name], $month, $period, $partial, $this->shopDashboards->dashboard($owner, $shop, $period), $this->shopDashboards->dashboard($owner, $shop, $previous)['metrics'], $previous);
        $payload['narrative'] = $this->narrative->shop($payload);

        return $this->store($owner, 'shop', $mall, $shop, $month, $partial, $payload);
    }

    public function generateForMall(User $administrator, Mall $mall, string $month): MonthlyReport
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        [$period, $previous, $partial] = $this->periods($mall, $month);

        $payload = $this->payload('mall', ['id' => (string) $mall->getKey(), 'name' => $mall->name], $month, $period, $partial, $this->mallDashboards->dashboard($administrator, $mall, $period), $this->mallDashboards->dashboard($administrator, $mall, $previous)['metrics'], $previous);
        $payload['narrative'] = $this->narrative->mall($payload);

        return $this->store($administrator, 'mall', $mall, null, $month, $partial, $payload);
    }

    /**
     * @return Collection<int, MonthlyReport>
     */
    public function forShop(User $owner, Shop $shop): Collection
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);

        return MonthlyReport::query()->where('scope', 'shop')->where('subject_key', $shop->getKey())->orderByDesc('month')->get();
    }

    /**
     * @return Collection<int, MonthlyReport>
     */
    public function forMall(User $administrator, Mall $mall): Collection
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        return MonthlyReport::query()->where('scope', 'mall')->where('subject_key', $mall->getKey())->orderByDesc('month')->get();
    }

    /**
     * The months offered for generation: the current month and the eleven before it, newest first.
     *
     * @return list<string>
     */
    public function selectableMonths(Mall $mall): array
    {
        $now = CarbonImmutable::now($mall->timezone)->startOfMonth();

        return array_map(fn (int $back): string => $now->subMonths($back)->format('Y-m'), range(0, 11));
    }

    /**
     * @return array{0: ReportPeriod, 1: ReportPeriod, 2: bool} the month, the month before, and whether the month is unfinished
     */
    private function periods(Mall $mall, string $month): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new BusinessRuleViolation('report.invalid_month', __('Choose a month as year-month, for example 2026-10.'));
        }

        $first = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', $mall->timezone);
        $today = CarbonImmutable::now($mall->timezone)->startOfDay();

        if ($first->gt($today)) {
            throw new BusinessRuleViolation('report.future_month', __('That month has not started yet.'));
        }

        $last = $first->endOfMonth()->startOfDay();
        $partial = $last->gte($today);
        $end = $partial ? $today : $last;
        $previousFirst = $first->subMonth();

        return [
            ReportPeriod::fromInput($mall, $first->format('Y-m-d'), $end->format('Y-m-d')),
            ReportPeriod::fromInput($mall, $previousFirst->format('Y-m-d'), $previousFirst->endOfMonth()->format('Y-m-d')),
            $partial,
        ];
    }

    /**
     * @param  array{id: string, name: string}  $subject
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $previousMetrics
     * @return array<string, mixed>
     */
    private function payload(string $scope, array $subject, string $month, ReportPeriod $period, bool $partial, array $dashboard, array $previousMetrics, ReportPeriod $previous): array
    {
        return [
            'scope' => $scope,
            'subject' => $subject,
            'month' => $month,
            'partial' => $partial,
            'period' => ['from' => $period->from->format('Y-m-d'), 'to' => $period->to->format('Y-m-d'), 'timezone' => $period->timezone],
            'metric_version' => ReportMetric::FORMULA_VERSION,
            'generated_at' => now()->toIso8601String(),
            'dashboard' => $dashboard,
            'previous' => ['month' => $previous->from->format('Y-m'), 'metrics' => $previousMetrics],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(User $actor, string $scope, Mall $mall, ?Shop $shop, string $month, bool $partial, array $payload): MonthlyReport
    {
        $subject = $shop?->getKey() ?? $mall->getKey();

        $report = MonthlyReport::query()->updateOrCreate(
            ['scope' => $scope, 'subject_key' => $subject, 'month' => $month.'-01'],
            ['mall_id' => $mall->getKey(), 'shop_id' => $shop?->getKey(), 'metric_version' => ReportMetric::FORMULA_VERSION, 'partial' => $partial, 'source_cutoff_at' => now(), 'payload' => $payload, 'generated_by_user_id' => $actor->getKey()],
        );

        $this->audit->record('report.generated', $report, ['scope' => $scope, 'month' => $month, 'partial' => $partial], (string) $mall->getKey(), $shop === null ? null : (string) $shop->getKey());

        return $report;
    }
}
