<?php

namespace App\Modules\AnalyticsReports\Services\Reporting;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\AnalyticsReports\Algorithms\EmpiricalBayes;
use App\Modules\AnalyticsReports\Algorithms\KMeans;
use App\Modules\AnalyticsReports\Algorithms\RobustStats;
use App\Modules\AnalyticsReports\Enums\DatasetLabel;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Services\ActivityReportService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\Ability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A mall's dashboard (Jira EPIC-12, decision D-017) for its administrators: tenant performance with
 * counts and coverage, segments, demand by category and floor, complaints and service quality, search
 * demand and page views. Navigation demand is listed as not available until the maps module exists. The
 * result holds plain arrays so a monthly report can store it.
 */
class MallDashboardService
{
    private const OPEN_COMPLAINT = ['open', 'assigned', 'in_progress'];

    private const RESOLVED_COMPLAINT = ['resolved', 'closed'];

    public function __construct(
        private readonly SourceData $source,
        private readonly SalesInsights $sales,
        private readonly FeedbackInsights $feedback,
        private readonly ActivityReportService $activity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $administrator, Mall $mall, ReportPeriod $period): array
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $start = $period->startsAt();
        $end = $period->endsAt();
        $mallId = (string) $mall->getKey();

        $shops = DB::table('shops')->join('shop_categories', 'shop_categories.shop_category_id', '=', 'shops.shop_category_id')
            ->where('shops.mall_id', $mallId)->where('shops.status', '!=', 'draft')
            ->get(['shops.shop_id', 'shops.name', 'shops.status', 'shop_categories.slug as category_slug', 'shop_categories.name as category_name']);
        $ids = $shops->pluck('shop_id')->map(fn ($id): string => (string) $id)->all();
        $floors = $this->shopFloors($ids);

        $totals = $this->source->salesTotals($ids, $start, $end);
        $perShop = $this->source->salesByShop($ids, $start, $end);
        $previous = $this->source->salesByShop($ids, $start->subDays($period->dayCount()), $start);
        $orders = $this->source->orderCounts($ids, $start, $end);
        $perShopOrders = $this->source->orderCountsByShop($ids, $start, $end);
        $returns = $this->source->completedReturns($ids, $start, $end);
        $promotions = $this->source->promotions($ids, $start, $end);
        $reviews = $this->source->reviews($ids, $start, $end);
        $views = $this->shopViews($mall, $period);
        $complaints = $this->source->complaints($mallId, $start, $end);

        $ratings = $this->ratings($reviews);
        $rating = EmpiricalBayes::shrink($ratings);
        $tenants = $this->tenants($shops->all(), $perShop, $previous, $perShopOrders, $ratings, $rating['estimates'], $views, $complaints, $floors);
        $segments = $this->segments($tenants);
        $complaintSummary = $this->complaintSummary($complaints);
        $flags = $this->qualityFlags($tenants, $complaints, $ids, $end, $period);
        $daily = $this->source->dailyRevenue($ids, $period);

        $search = $this->activity->searchDemand($administrator, $mall, $period, DatasetLabel::Real);
        $pageViews = $this->activity->mallViews($administrator, $mall, $period, DatasetLabel::Real);

        return [
            'metrics' => [
                ReportMetric::SalesRevenue->value => $totals['revenue'],
                ReportMetric::SalesCount->value => $totals['sales'],
                ReportMetric::AverageSaleValue->value => $totals['sales'] > 0 ? round($totals['revenue'] / $totals['sales'], 2) : null,
                ReportMetric::UnitsSold->value => $totals['units'],
                ReportMetric::OrdersPlaced->value => $orders['placed'],
                ReportMetric::OrdersCompleted->value => $orders['completed'],
                ReportMetric::ReservationsPlaced->value => $orders['reservations'],
                ReportMetric::OrderCompletionRate->value => $orders['placed'] > 0 ? round($orders['completed'] / $orders['placed'] * 100, 1) : null,
                ReportMetric::ReturnsCompleted->value => $returns['count'],
                ReportMetric::ReturnsValue->value => $returns['value'],
                ReportMetric::ReviewsPublished->value => count($reviews),
                ReportMetric::RatingAverage->value => $reviews === [] ? null : round(array_sum(array_column($reviews, 'rating')) / count($reviews), 2),
                ReportMetric::CouponRedemptions->value => array_sum(array_column($promotions, 'redemptions')),
                ReportMetric::DiscountGiven->value => round(array_sum(array_column($promotions, 'discount')), 2),
                ReportMetric::ActiveShops->value => $shops->where('status', 'active')->count(),
                ReportMetric::ShopsWithSales->value => count(array_filter($perShop, fn (array $s): bool => $s['sales'] > 0)),
                ReportMetric::ComplaintsFiled->value => $complaintSummary['total'],
                ReportMetric::ComplaintsResolved->value => $complaintSummary['resolved'],
                ReportMetric::ComplaintsUnresolved->value => $complaintSummary['unresolved'],
                ReportMetric::ComplaintResolutionHours->value => $complaintSummary['median_hours'],
                ReportMetric::NavigationRequests->value => null,
            ],
            'period' => ['from' => $period->from->format('Y-m-d'), 'to' => $period->to->format('Y-m-d'), 'timezone' => $period->timezone, 'days' => $period->dayCount()],
            'daily_sales' => $daily,
            'anomalies' => $this->sales->anomalies($daily, CarbonImmutable::now($period->timezone)->format('Y-m-d')),
            'tenants' => $tenants,
            'rating_model' => ['grand_mean' => round($rating['grand_mean'], 2), 'prior_weight' => round($rating['prior_weight'], 1), 'estimated' => $rating['estimated']],
            'segments' => $segments,
            'categories' => $this->demand($tenants, 'category', $this->searchesBy($mall, $period, 'search_category_count')),
            'floors' => $this->demand($tenants, 'floor', $this->searchesBy($mall, $period, 'search_floor_count')),
            'complaints' => $complaintSummary,
            'quality_flags' => $flags,
            'feedback' => $this->feedback->summary($reviews),
            'navigation' => ['available' => false, 'reason' => ReportMetric::NavigationRequests->unavailableReason()],
            'search' => ['searches' => $search['searches'], 'zero_results' => $search['zero_results'], 'unmet' => $search['unmet']->take(5)->all()],
            'page_views' => ['shop_views' => $pageViews['shop_views'], 'shop_visitors' => $pageViews['shop_visitors']],
            'coverage' => [
                'shops' => $shops->count(),
                'shops_with_sales' => count(array_filter($perShop, fn (array $s): bool => $s['sales'] > 0)),
                'shops_with_reviews' => count($ratings),
                'shops_with_five_reviews' => count(array_filter($ratings, fn (array $r): bool => $r['n'] >= 5)),
            ],
            'provenance' => $this->source->provenance($ids, $start, $end),
            'source_cutoff' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{code: string, name: string}> shop id => floor of its first non-archived unit
     */
    private function shopFloors(array $ids): array
    {
        $floors = [];

        $rows = DB::table('shop_units')->join('floors', 'floors.floor_id', '=', 'shop_units.floor_id')->whereIn('shop_units.shop_id', $ids)->where('shop_units.status', '!=', 'archived')
            ->orderBy('floors.sort_order')->orderBy('shop_units.unit_code')->get(['shop_units.shop_id', 'floors.code', 'floors.name']);

        foreach ($rows as $row) {
            $floors[(string) $row->shop_id] ??= ['code' => (string) $row->code, 'name' => (string) $row->name];
        }

        return $floors;
    }

    /**
     * @return array<string, int> shop id => real page views of the shop in the period
     */
    private function shopViews(Mall $mall, ReportPeriod $period): array
    {
        return DB::table('activity_daily_summaries')->where('mall_id', $mall->getKey())->where('metric_code', 'shop_page_views')->where('dataset_label', DatasetLabel::Real->value)
            ->where('day', '>=', $period->from->format('Y-m-d'))->where('day', '<=', $period->to->format('Y-m-d'))
            ->selectRaw('subject_key, sum(value) as views')->groupBy('subject_key')->pluck('views', 'subject_key')->map(fn ($v): int => (int) $v)->all();
    }

    /**
     * @param  list<array{shop: string, rating: int, body: string, at: CarbonImmutable}>  $reviews
     * @return array<string, array{n: int, sum: float, sum_squares: float}>
     */
    private function ratings(array $reviews): array
    {
        $groups = [];

        foreach ($reviews as $review) {
            $groups[$review['shop']] ??= ['n' => 0, 'sum' => 0.0, 'sum_squares' => 0.0];
            $groups[$review['shop']]['n']++;
            $groups[$review['shop']]['sum'] += $review['rating'];
            $groups[$review['shop']]['sum_squares'] += $review['rating'] ** 2;
        }

        return $groups;
    }

    /**
     * @param  array<int, object>  $shops
     * @param  array<string, array{revenue: float, sales: int}>  $perShop
     * @param  array<string, array{revenue: float, sales: int}>  $previous
     * @param  array<string, array{placed: int, completed: int}>  $orders
     * @param  array<string, array{n: int, sum: float, sum_squares: float}>  $ratings
     * @param  array<string, float>  $adjusted
     * @param  array<string, int>  $views
     * @param  list<array{number: string, shop: ?string, category: string, status: string, filed: CarbonImmutable, closed: ?CarbonImmutable}>  $complaints
     * @param  array<string, array{code: string, name: string}>  $floors
     * @return list<array<string, mixed>>
     */
    private function tenants(array $shops, array $perShop, array $previous, array $orders, array $ratings, array $adjusted, array $views, array $complaints, array $floors): array
    {
        $totalRevenue = array_sum(array_column($perShop, 'revenue'));
        $complaintCounts = array_count_values(array_filter(array_column($complaints, 'shop')));
        $rows = [];

        foreach ($shops as $shop) {
            $id = (string) $shop->shop_id;
            $revenue = $perShop[$id]['revenue'] ?? 0.0;
            $placed = $orders[$id]['placed'] ?? 0;

            if ($shop->status !== 'active' && $revenue <= 0 && $placed === 0) {
                continue;
            }

            $before = $previous[$id]['revenue'] ?? 0.0;
            $n = $ratings[$id]['n'] ?? 0;

            $rows[] = [
                'shop_id' => $id,
                'name' => (string) $shop->name,
                'category_slug' => (string) $shop->category_slug,
                'category' => (string) $shop->category_name,
                'floor' => $floors[$id]['name'] ?? null,
                'floor_code' => $floors[$id]['code'] ?? null,
                'revenue' => $revenue,
                'revenue_share' => $totalRevenue > 0 ? round($revenue / $totalRevenue * 100, 1) : 0.0,
                'previous_revenue' => $before,
                'change_percent' => $before > 0 ? round(($revenue - $before) / $before * 100, 1) : null,
                'sales' => $perShop[$id]['sales'] ?? 0,
                'orders' => $placed,
                'orders_completed' => $orders[$id]['completed'] ?? 0,
                'completion_lower' => ($lower = EmpiricalBayes::wilsonLower($orders[$id]['completed'] ?? 0, $placed)) === null ? null : round($lower * 100, 1),
                'reviews' => $n,
                'rating' => $n > 0 ? round($ratings[$id]['sum'] / $n, 2) : null,
                'rating_adjusted' => isset($adjusted[$id]) ? round($adjusted[$id], 2) : null,
                'views' => $views[$id] ?? 0,
                'complaints' => $complaintCounts[$id] ?? 0,
                'segment' => null,
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['revenue'], $a['name']] <=> [$a['revenue'], $b['name']]);

        return $rows;
    }

    /**
     * Groups the shops with activity into segments by k-means on standardised features and writes each
     * shop's segment name into $tenants (by reference through the returned structure).
     *
     * @param  list<array<string, mixed>>  $tenants
     * @return array{available: bool, reason: ?string, k: ?int, silhouette: ?float, groups: list<array{name: string, shops: list<string>, mean_revenue: float, mean_rating: ?float}>}
     */
    private function segments(array &$tenants): array
    {
        $active = array_values(array_filter($tenants, fn (array $t): bool => $t['revenue'] > 0 || $t['orders'] > 0 || $t['views'] > 0));

        if (count($active) < 5) {
            return ['available' => false, 'reason' => __('Needs at least 5 shops with sales, orders or views in the period (this period has :count).', ['count' => count($active)]), 'k' => null, 'silhouette' => null, 'groups' => []];
        }

        $points = RobustStats::standardise(array_map(fn (array $t): array => [log(1 + $t['revenue']), log(1 + $t['sales']), (float) ($t['rating_adjusted'] ?? 0.0), log(1 + $t['views'])], $active));
        $best = KMeans::best($points, 2, 4);

        if ($best === null) {
            return ['available' => false, 'reason' => __('The shops are too alike to separate into segments.'), 'k' => null, 'silhouette' => null, 'groups' => []];
        }

        $clusters = [];

        foreach ($best['labels'] as $i => $label) {
            $clusters[$label][] = $i;
        }

        uasort($clusters, fn (array $a, array $b): int => $this->mean($b, $active, 'revenue') <=> $this->mean($a, $active, 'revenue'));

        $names = match (count($clusters)) {
            2 => [__('Higher activity'), __('Lower activity')],
            3 => [__('Higher activity'), __('Middle activity'), __('Lower activity')],
            default => [__('Highest activity'), __('Higher activity'), __('Lower activity'), __('Lowest activity')],
        };

        $groups = [];
        $segmentOf = [];

        foreach (array_values($clusters) as $rank => $members) {
            $ratings = array_filter(array_map(fn (int $i): ?float => $active[$i]['rating_adjusted'], $members), fn (?float $r): bool => $r !== null);

            foreach ($members as $i) {
                $segmentOf[$active[$i]['shop_id']] = $names[$rank];
            }

            $groups[] = [
                'name' => $names[$rank],
                'shops' => array_map(fn (int $i): string => $active[$i]['name'], $members),
                'mean_revenue' => round($this->mean($members, $active, 'revenue'), 2),
                'mean_rating' => $ratings === [] ? null : round(array_sum($ratings) / count($ratings), 2),
            ];
        }

        foreach ($tenants as &$tenant) {
            $tenant['segment'] = $segmentOf[$tenant['shop_id']] ?? null;
        }

        unset($tenant);

        return ['available' => true, 'reason' => null, 'k' => count($clusters), 'silhouette' => round($best['silhouette'], 2), 'groups' => $groups];
    }

    /**
     * @param  list<int>  $members
     * @param  list<array<string, mixed>>  $rows
     */
    private function mean(array $members, array $rows, string $key): float
    {
        return array_sum(array_map(fn (int $i): float => (float) $rows[$i][$key], $members)) / count($members);
    }

    /**
     * @param  list<array<string, mixed>>  $tenants
     * @param  array<string, int>  $searches  slug or code => searches
     * @return list<array{name: string, shops: int, revenue: float, share: float, orders: int, views: int, searches: int}>
     */
    private function demand(array $tenants, string $by, array $searches): array
    {
        $groups = [];
        $total = array_sum(array_column($tenants, 'revenue'));

        foreach ($tenants as $tenant) {
            $key = $by === 'category' ? $tenant['category_slug'] : ($tenant['floor_code'] ?? '');
            $name = $by === 'category' ? $tenant['category'] : ($tenant['floor'] ?? __('No floor assigned'));
            $groups[$key] ??= ['key' => $key, 'name' => $name, 'shops' => 0, 'revenue' => 0.0, 'orders' => 0, 'views' => 0];
            $groups[$key]['shops']++;
            $groups[$key]['revenue'] += $tenant['revenue'];
            $groups[$key]['orders'] += $tenant['orders'];
            $groups[$key]['views'] += $tenant['views'];
        }

        $rows = array_map(fn (array $g): array => [
            'name' => $g['name'], 'shops' => $g['shops'], 'revenue' => round($g['revenue'], 2), 'share' => $total > 0 ? round($g['revenue'] / $total * 100, 1) : 0.0,
            'orders' => $g['orders'], 'views' => $g['views'], 'searches' => $searches[$g['key']] ?? 0,
        ], array_values($groups));

        usort($rows, fn (array $a, array $b): int => [$b['revenue'], $a['name']] <=> [$a['revenue'], $b['name']]);

        return $rows;
    }

    /**
     * @return array<string, int> category slug or floor code => searches that used the filter
     */
    private function searchesBy(Mall $mall, ReportPeriod $period, string $metric): array
    {
        return DB::table('activity_daily_summaries')->where('mall_id', $mall->getKey())->where('metric_code', $metric)->where('dataset_label', DatasetLabel::Real->value)
            ->where('day', '>=', $period->from->format('Y-m-d'))->where('day', '<=', $period->to->format('Y-m-d'))
            ->selectRaw('subject_key, sum(value) as total')->groupBy('subject_key')->pluck('total', 'subject_key')->map(fn ($v): int => (int) $v)->all();
    }

    /**
     * @param  list<array{number: string, shop: ?string, category: string, status: string, filed: CarbonImmutable, closed: ?CarbonImmutable}>  $complaints
     * @return array{total: int, resolved: int, unresolved: int, median_hours: ?float, by_category: array<string, int>, by_status: array<string, int>}
     */
    private function complaintSummary(array $complaints): array
    {
        $byCategory = $byStatus = [];
        $hours = [];

        foreach ($complaints as $complaint) {
            $byCategory[$complaint['category']] = ($byCategory[$complaint['category']] ?? 0) + 1;
            $byStatus[$complaint['status']] = ($byStatus[$complaint['status']] ?? 0) + 1;

            if (in_array($complaint['status'], self::RESOLVED_COMPLAINT, true) && $complaint['closed'] !== null) {
                $hours[] = ($complaint['closed']->getTimestamp() - $complaint['filed']->getTimestamp()) / 3600;
            }
        }

        arsort($byCategory);
        ksort($byStatus);
        $resolved = array_sum(array_intersect_key($byStatus, array_flip(self::RESOLVED_COMPLAINT)));

        return [
            'total' => count($complaints),
            'resolved' => $resolved,
            'unresolved' => array_sum(array_intersect_key($byStatus, array_flip(self::OPEN_COMPLAINT))),
            'median_hours' => $hours === [] ? null : round(RobustStats::median($hours), 1),
            'by_category' => $byCategory,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Shops that deserve a look, each with the evidence behind the flag.
     *
     * @param  list<array<string, mixed>>  $tenants
     * @param  list<array{number: string, shop: ?string, category: string, status: string, filed: CarbonImmutable, closed: ?CarbonImmutable}>  $complaints
     * @param  list<string>  $ids
     * @return list<array{shop: string, kind: string, evidence: string}>
     */
    private function qualityFlags(array $tenants, array $complaints, array $ids, CarbonImmutable $end, ReportPeriod $period): array
    {
        $flags = [];
        $names = array_column($tenants, 'name', 'shop_id');
        $window = $this->source->reviews($ids, $end->subDays(FeedbackInsights::TREND_WINDOW_DAYS), $end);
        $byShop = [];

        foreach ($window as $review) {
            $byShop[$review['shop']][] = $review;
        }

        foreach ($tenants as $tenant) {
            $trend = $this->feedback->trend($byShop[$tenant['shop_id']] ?? []);

            if ($trend['available'] && $trend['significant'] && $trend['slope_per_30_days'] <= -0.25) {
                $flags[] = ['shop' => $tenant['name'], 'kind' => 'rating_decline', 'evidence' => __('Ratings fall by :slope stars per 30 days (p :p, :n reviews in the last :days days).', ['slope' => number_format(abs($trend['slope_per_30_days']), 2), 'p' => FeedbackInsights::formatP($trend['p_value']), 'n' => $trend['reviews'], 'days' => FeedbackInsights::TREND_WINDOW_DAYS])];
            }

            if ($tenant['change_percent'] !== null && $tenant['change_percent'] <= -40 && $tenant['previous_revenue'] >= 1000) {
                $flags[] = ['shop' => $tenant['name'], 'kind' => 'revenue_drop', 'evidence' => __('Recorded sales fell :percent% from BDT :before in the previous :days days to BDT :now.', ['percent' => number_format(abs($tenant['change_percent']), 1), 'before' => number_format($tenant['previous_revenue'], 2), 'days' => $period->dayCount(), 'now' => number_format($tenant['revenue'], 2)])];
            }
        }

        $stale = [];

        foreach ($complaints as $complaint) {
            if ($complaint['shop'] !== null && in_array($complaint['status'], self::OPEN_COMPLAINT, true) && $complaint['filed']->lt(now()->subDays(7))) {
                $stale[$complaint['shop']][] = $complaint['number'];
            }
        }

        foreach ($stale as $shop => $numbers) {
            if (count($numbers) >= 3 && isset($names[$shop])) {
                $flags[] = ['shop' => $names[$shop], 'kind' => 'unresolved_complaints', 'evidence' => __(':count complaints are still open after more than 7 days: :numbers.', ['count' => count($numbers), 'numbers' => implode(', ', $numbers)])];
            }
        }

        $counts = array_map(fn (array $t): float => (float) $t['complaints'], $tenants);

        if (count($counts) >= 4) {
            foreach (RobustStats::modifiedZScores($counts) as $i => $z) {
                if ($z >= 3.5 && $tenants[$i]['complaints'] >= 3) {
                    $flags[] = ['shop' => $tenants[$i]['name'], 'kind' => 'complaint_outlier', 'evidence' => __(':count complaints in the period against a middle value of :median among shops (modified z-score :z).', ['count' => $tenants[$i]['complaints'], 'median' => RobustStats::median($counts), 'z' => number_format($z, 1)])];
                }
            }
        }

        usort($flags, fn (array $a, array $b): int => [$a['shop'], $a['kind']] <=> [$b['shop'], $b['kind']]);

        return $flags;
    }
}
