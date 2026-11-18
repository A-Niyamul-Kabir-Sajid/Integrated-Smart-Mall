<?php

namespace App\Modules\ChatbotRag\Services\Assistant\Tools;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AnalyticsReports\Enums\ReportMetric;
use App\Modules\AnalyticsReports\Services\Reporting\MallDashboardService;
use App\Modules\AnalyticsReports\Services\Reporting\SourceData;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\ChatbotRag\DTOs\ToolResultData;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Answers an administrator's question with figures of the metric catalogue (Jira ISM-135).
 *
 * Every number comes from AnalyticsReports: the mall dashboard of the period asked for, which checks
 * that the asker administers the mall, and its complaint records for the per-shop breakdown. The
 * answer is built from fixed sentences around those numbers, with the period, the definition of
 * each figure and the share of simulated records, so it always equals what the dashboard shows. A
 * figure that is not in the catalogue, belongs to shop owners or has no data source is named as
 * such; nothing is estimated and no model is involved.
 */
class MetricTool
{
    public const LIST_LIMIT = 5;

    /**
     * Words that ask for one figure of the catalogue. Checked in this order; all that match are answered.
     */
    private const FIGURES = [
        'complaint_resolution_hours' => '/\b(time|how long|hours)\b[^.?!]{0,30}\bresolv|\bresolution time\b/',
        'complaints_resolved' => '/\bcomplaints?\b[^.?!]{0,20}\bresolved\b|\bresolved complaints?\b/',
        'complaints_unresolved' => '/\b(open|unresolved|pending|outstanding)\s+complaints?\b|\bcomplaints?\b[^.?!]{0,20}\b(still open|unresolved|pending)\b/',
        'complaints_filed' => '/\bcomplaints?\b/',
        'average_sale_value' => '/\baverage\b[^.?!]{0,15}\b(sale|basket|ticket|transaction|purchase)\b/',
        'units_sold' => '/\bunits?\b[^.?!]{0,10}\bsold\b|\bitems?\b[^.?!]{0,10}\bsold\b/',
        'sales_count' => '/\b(number|count) of sales\b|\bhow many sales\b|\btransactions\b/',
        'sales_revenue' => '/\b(revenue|turnover|earnings?|income|takings)\b|\bhow much\b[^.?!]{0,30}\b(sell|sold|sales|earn|earned|make|made)\b/',
        'order_completion_rate' => '/\bcompletion rate\b|\bcompleted\b[^.?!]{0,15}\bpercent/',
        'orders_completed' => '/\b(completed|collected|fulfilled)\s+orders?\b|\borders?\b[^.?!]{0,15}\b(completed|collected)\b/',
        'reservations_placed' => '/\breservations?\b/',
        'orders_placed' => '/\borders?\b/',
        'returns_value' => '/\b(value|amount|worth)\b[^.?!]{0,15}\breturns?\b|\brefunded\b/',
        'returns_completed' => '/\b(returns?|exchanges?)\b/',
        'rating_average' => '/\b(average rating|ratings?|stars)\b/',
        'reviews_published' => '/\breviews?\b/',
        'coupon_redemptions' => '/\b(redemptions?|coupons?|promotions?)\b/',
        'discount_given' => '/\bdiscounts?\b/',
        'shops_with_sales' => '/\bshops?\b[^.?!]{0,15}\bwith\b[^.?!]{0,10}\bsales?\b/',
        'active_shops' => '/\bactive shops?\b|\bhow many (shops|tenants|stores)\b|\bnumber of (shops|tenants|stores)\b/',
        'navigation_requests' => '/\b(navigation|route requests?|directions requested|footfall|foot traffic|visits to the mall)\b/',
    ];

    private const SALES_SUMMARY = ['sales_revenue', 'sales_count', 'average_sale_value', 'units_sold'];

    private const COMPLAINT_CATEGORIES = ['cleanliness' => 'cleanliness', 'safety' => 'safety', 'staff conduct' => 'staff_conduct', 'staff' => 'staff_conduct', 'facilities' => 'facilities', 'facility' => 'facilities', 'shop service' => 'shop_service', 'service' => 'shop_service', 'noise' => 'noise'];

    public function __construct(
        private MallDashboardService $dashboards,
        private SourceData $source,
        private PeriodParser $periods,
    ) {}

    public function run(Mall $mall, User $administrator, string $question): ToolResultData
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $text = mb_strtolower($question);

        if (preg_match('/\b(stock|inventory|out of stock|low stock|units in stock)\b/', $text) === 1) {
            return $this->fixed(AnswerOutcome::Refused, __('Stock figures belong to each shop and are shown only to its owner; they are not part of the mall\'s figures.'), ['refusal_reason' => 'not_in_scope']);
        }

        if (preg_match('/\b(forecast|predict|prediction|projection|projected|next (week|month|year)|will (we|sales|revenue))\b/', $text) === 1) {
            return $this->fixed(AnswerOutcome::Answered, __('I do not estimate future figures. The sales forecast page shows the prototype forecast with its method, errors and limits.'), [
                'links' => Route::has('mall-admin.analytics.forecast') ? [['label' => 'Sales forecast', 'url' => route('mall-admin.analytics.forecast', $mall)]] : [],
            ]);
        }

        try {
            ['period' => $period, 'named' => $named] = $this->periods->parse($text, $mall);
        } catch (BusinessRuleViolation $violation) {
            return $this->fixed(AnswerOutcome::NoAnswer, $violation->getMessage());
        }

        $dashboard = $this->dashboards->dashboard($administrator, $mall, $period);
        $lines = $this->breakdown($text, $mall, $period, $dashboard) ?? $this->figures($text, $dashboard);

        if ($lines === null) {
            return $this->fixed(AnswerOutcome::NoAnswer, __('I can report only figures of the metric catalogue: sales, orders, reservations, returns, reviews and ratings, promotions, complaints, active shops, searches visitors made, and rankings of shops by sales, rating or complaints. Ask for one of them with a period, for example "complaints last month".'), [
                'links' => $this->links($mall, $period),
            ]);
        }

        $lines['text'][] = __('Period: :from to :to (:days days, :timezone time):note. Figures read at :time.', [
            'from' => $period->from->format('j M Y'), 'to' => $period->to->format('j M Y'), 'days' => $period->dayCount(), 'timezone' => $period->timezone,
            'note' => $named ? '' : ', '.__('the default period because the question named none'),
            'time' => now($period->timezone)->format('j M Y H:i'),
        ]);

        if ($dashboard['provenance']['any']) {
            $lines['text'][] = __('Note: this period includes simulated demonstration records (:sales of :total_sales sales, :orders of :total_orders orders, :reviews of :total_reviews reviews).', [
                'sales' => $dashboard['provenance']['sales']['simulated'], 'total_sales' => $dashboard['provenance']['sales']['total'],
                'orders' => $dashboard['provenance']['orders']['simulated'], 'total_orders' => $dashboard['provenance']['orders']['total'],
                'reviews' => $dashboard['provenance']['reviews']['simulated'], 'total_reviews' => $dashboard['provenance']['reviews']['total'],
            ]);
        }

        return $this->fixed(AnswerOutcome::Answered, implode("\n", $lines['text']), [
            'definitions' => $lines['definitions'],
            'period' => ['from' => $period->from->format('Y-m-d'), 'to' => $period->to->format('Y-m-d'), 'timezone' => $period->timezone],
            'links' => $this->links($mall, $period),
        ]);
    }

    /**
     * The figures the question names, each with its definition.
     *
     * @param  array<string, mixed>  $dashboard
     * @return array{text: list<string>, definitions: list<array{label: string, formula: string}>}|null
     */
    private function figures(string $text, array $dashboard): ?array
    {
        $codes = [];

        foreach (self::FIGURES as $code => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $codes[] = $code;
            }
        }

        if ($codes === [] && preg_match('/\bsales?\b|\bsold\b|\bselling\b/', $text) === 1) {
            $codes = self::SALES_SUMMARY;
        }

        // "complaints resolved" also matches "complaints"; the more specific figure answers.
        if (count(array_intersect($codes, ['complaints_resolved', 'complaints_unresolved', 'complaint_resolution_hours'])) > 0) {
            $codes = array_values(array_diff($codes, ['complaints_filed']));
        }

        foreach ([['orders_completed', 'orders_placed'], ['order_completion_rate', 'orders_placed'], ['returns_value', 'returns_completed'], ['rating_average', 'reviews_published'], ['coupon_redemptions', 'discount_given']] as [$specific, $general]) {
            if (in_array($specific, $codes, true) && preg_match(self::FIGURES[$general], (string) preg_replace(self::FIGURES[$specific], ' ', $text)) !== 1) {
                $codes = array_values(array_diff($codes, [$general]));
            }
        }

        if ($codes === []) {
            return null;
        }

        $text = [];
        $definitions = [];

        foreach ($codes as $code) {
            $metric = ReportMetric::from($code);

            if (! $metric->visibleToMall()) {
                continue;
            }

            $text[] = $metric->label().': '.($metric->available() ? $this->format($metric, $dashboard['metrics'][$code] ?? null) : $metric->unavailableReason());
            $definitions[] = ['label' => $metric->label(), 'formula' => $metric->formula()];
        }

        return $text === [] ? null : ['text' => $text, 'definitions' => $definitions];
    }

    /**
     * A ranking or grouping the dashboard already holds, when the question asks for one.
     *
     * @param  array<string, mixed>  $dashboard
     * @return array{text: list<string>, definitions: list<array{label: string, formula: string}>}|null
     */
    private function breakdown(string $text, Mall $mall, ReportPeriod $period, array $dashboard): ?array
    {
        $shops = '(shops?|tenants?|stores?)';
        $definition = fn (ReportMetric ...$metrics): array => array_map(fn (ReportMetric $metric): array => ['label' => $metric->label(), 'formula' => $metric->formula()], $metrics);

        if (preg_match('/\bcomplaints?\b/', $text) === 1 && preg_match('/\b(which|what|top|most|by|per|each)\b[^.?!]{0,40}\b'.$shops.'\b|\b'.$shops.'\b[^.?!]{0,40}\bmost\b/', $text) === 1) {
            return ['text' => $this->complaintsByShop($text, $mall, $period, $dashboard), 'definitions' => $definition(ReportMetric::ComplaintsFiled)];
        }

        if (preg_match('/\bcomplaints?\b[^.?!]{0,30}\b(by|per) (category|type|kind)\b|\b(kinds?|types?|categories) of complaints?\b|\bcomplain(ed|ing)? about\b/', $text) === 1) {
            $rows = $dashboard['complaints']['by_category'];

            return ['text' => $rows === []
                ? [__('No complaint was filed in this period.')]
                : [__('Complaints filed by category:'), ...array_map(fn (string $category, int $count): string => '• '.str_replace('_', ' ', $category).': '.$count, array_keys($rows), $rows)],
                'definitions' => $definition(ReportMetric::ComplaintsFiled)];
        }

        if (preg_match('/\b(quality flags?|flags?|flagged|need(s)? attention|at risk)\b/', $text) === 1) {
            return ['text' => $dashboard['quality_flags'] === []
                ? [__('No service-quality flag was raised in this period.')]
                : [__('Service-quality flags (prompts to look, with their evidence):'), ...array_map(fn (array $flag): string => '• '.$flag['shop'].' ('.str_replace('_', ' ', $flag['kind']).'): '.$flag['evidence'], $dashboard['quality_flags'])],
                'definitions' => $definition(ReportMetric::QualityFlags)];
        }

        if (preg_match('/\b(unmet|no results?|not find|cannot find|can\'t find|could not find|couldn\'t find|search demand|searched|searches|searching)\b/', $text) === 1) {
            $search = $dashboard['search'];
            $lines = [__('Searches by visitors: :searches, of which :none found nothing.', ['searches' => $search['searches'], 'none' => $search['zero_results']])];

            foreach ($search['unmet'] as $term) {
                $lines[] = '• "'.$term['term'].'": '.__('searched without a result :count times', ['count' => $term['zero_results']]);
            }

            return ['text' => $lines, 'definitions' => [['label' => __('Search demand'), 'formula' => __('First-page searches on the shop and product search by real visitors; a search is unmet when it returned no result. Staff, crawlers and repeats within 30 minutes are not counted.')]]];
        }

        if (preg_match('/\b(rated|ratings?|stars)\b/', $text) === 1 && preg_match('/\b'.$shops.'\b/', $text) === 1) {
            $rated = array_values(array_filter($dashboard['tenants'], fn (array $tenant): bool => $tenant['reviews'] > 0));
            $lowest = preg_match('/\b(worst|lowest|poorest|bottom|least)\b/', $text) === 1;
            usort($rated, fn (array $a, array $b): int => $lowest ? [$a['rating_adjusted'], $a['name']] <=> [$b['rating_adjusted'], $b['name']] : [$b['rating_adjusted'], $a['name']] <=> [$a['rating_adjusted'], $b['name']]);

            return ['text' => $rated === []
                ? [__('No shop received a published review in this period.')]
                : [$lowest ? __('Shops by adjusted rating, lowest first:') : __('Shops by adjusted rating, highest first:'), ...array_map(
                    fn (array $tenant): string => '• '.$tenant['name'].': '.__('adjusted :adjusted, average :average from :count reviews', ['adjusted' => number_format((float) $tenant['rating_adjusted'], 2), 'average' => number_format((float) $tenant['rating'], 2), 'count' => $tenant['reviews']]),
                    array_slice($rated, 0, self::LIST_LIMIT),
                )],
                'definitions' => $definition(ReportMetric::RatingAdjusted, ReportMetric::RatingAverage)];
        }

        if (preg_match('/\b(top|best|leading|highest|biggest|worst|lowest|bottom|weakest|rank|ranking)\b[^.?!]{0,30}\b'.$shops.'\b|\bwhich '.$shops.'\b[^.?!]{0,30}\b(sold|sell|earned|made|most|least)\b/', $text) === 1) {
            $tenants = $dashboard['tenants'];
            $lowest = preg_match('/\b(worst|lowest|bottom|weakest|least)\b/', $text) === 1;

            if ($lowest) {
                $tenants = array_reverse($tenants);
            }

            return ['text' => $tenants === []
                ? [__('No shop had activity in this period.')]
                : [$lowest ? __('Shops by recorded sales, lowest first:') : __('Shops by recorded sales, highest first:'), ...array_map(
                    fn (array $tenant): string => '• '.$tenant['name'].': BDT '.number_format((float) $tenant['revenue'], 2).' ('.__(':share% of the mall, :sales sales', ['share' => number_format((float) $tenant['revenue_share'], 1), 'sales' => $tenant['sales']]).')',
                    array_slice($tenants, 0, self::LIST_LIMIT),
                )],
                'definitions' => $definition(ReportMetric::SalesRevenue, ReportMetric::SalesCount)];
        }

        foreach (['categories' => ['/\b(by|per|each) categor|\bcategories\b/', ReportMetric::CategoryDemand, __('Demand by shop category:')], 'floors' => ['/\b(by|per|each) floor\b|\bfloors\b/', ReportMetric::FloorDemand, __('Demand by floor:')]] as $key => [$pattern, $metric, $heading]) {
            if (preg_match($pattern, $text) === 1) {
                return ['text' => [$heading, ...array_map(
                    fn (array $row): string => '• '.$row['name'].': BDT '.number_format((float) $row['revenue'], 2).', '.__(':orders orders, :shops shops', ['orders' => $row['orders'], 'shops' => $row['shops']]),
                    $dashboard[$key],
                )], 'definitions' => $definition($metric)];
            }
        }

        return null;
    }

    /**
     * Complaints per shop, for one category when the question names it.
     *
     * @param  array<string, mixed>  $dashboard
     * @return list<string>
     */
    private function complaintsByShop(string $text, Mall $mall, ReportPeriod $period, array $dashboard): array
    {
        $category = null;

        foreach (self::COMPLAINT_CATEGORIES as $word => $code) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $text) === 1) {
                $category = $code;

                break;
            }
        }

        $names = array_column($dashboard['tenants'], 'name', 'shop_id');
        $counts = [];
        $aboutTheMall = 0;

        foreach ($this->source->complaints((string) $mall->getKey(), $period->startsAt(), $period->endsAt()) as $complaint) {
            if ($category !== null && $complaint['category'] !== $category) {
                continue;
            }

            if ($complaint['shop'] === null || ! isset($names[$complaint['shop']])) {
                $aboutTheMall++;
            } else {
                $counts[$names[$complaint['shop']]] = ($counts[$names[$complaint['shop']]] ?? 0) + 1;
            }
        }

        $label = $category === null ? __('complaints') : __(':category complaints', ['category' => str_replace('_', ' ', $category)]);

        if ($counts === []) {
            return [__('No shop received :label in this period.', ['label' => $label]).($aboutTheMall > 0 ? ' '.__(':count were about the mall itself.', ['count' => $aboutTheMall]) : '')];
        }

        uksort($counts, fn (string $a, string $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);
        $lines = [__('Shops with the most :label:', ['label' => $label])];

        foreach (array_slice($counts, 0, self::LIST_LIMIT, true) as $shop => $count) {
            $lines[] = '• '.$shop.': '.$count;
        }

        if ($aboutTheMall > 0) {
            $lines[] = __(':count more were about the mall itself, not a shop.', ['count' => $aboutTheMall]);
        }

        return $lines;
    }

    private function format(ReportMetric $metric, mixed $value): string
    {
        if ($value === null) {
            return __('no figure for this period (nothing to calculate it from)');
        }

        return match ($metric->unit()) {
            'BDT' => 'BDT '.number_format((float) $value, 2),
            '%' => number_format((float) $value, 1).'%',
            'stars' => number_format((float) $value, 2).' '.__('of 5 stars'),
            'hours' => number_format((float) $value, 1).' '.__('hours'),
            default => number_format((float) $value).' '.$metric->unit(),
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function fixed(AnswerOutcome $outcome, string $text, array $meta = []): ToolResultData
    {
        return new ToolResultData($outcome, fixedText: $text, meta: $meta);
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function links(Mall $mall, ReportPeriod $period): array
    {
        $links = [];

        if (Route::has('mall-admin.analytics.index')) {
            $links[] = ['label' => 'Dashboard for this period', 'url' => route('mall-admin.analytics.index', ['mall' => $mall, 'from' => $period->from->format('Y-m-d'), 'to' => $period->to->format('Y-m-d')])];
        }

        if (Route::has('mall-admin.analytics.metrics')) {
            $links[] = ['label' => 'How each figure is calculated', 'url' => route('mall-admin.analytics.metrics', $mall)];
        }

        return $links;
    }
}
