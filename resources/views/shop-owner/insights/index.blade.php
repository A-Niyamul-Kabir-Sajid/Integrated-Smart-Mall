@php
    $m = $report['metrics'];
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $feedback = $report['feedback'];
@endphp
<x-layouts.customer :title="__('Sales and performance').' · '.$shop->name">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Sales and performance') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Recorded sales, orders and reservations, product performance, stock, customer feedback and promotions of your shop. Sales are completed counter sales and collected orders recorded in the system; a page view or an order that was never collected is not a sale.') }}</p>
        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            <a href="{{ route('workspace.insights.forecast', $shop) }}" class="font-medium text-brand-700 hover:underline">{{ __('Sales forecast (prototype)') }}</a>
            <a href="{{ route('workspace.insights.reports.index', $shop) }}" class="font-medium text-brand-700 hover:underline">{{ __('Monthly reports') }}</a>
            <a href="{{ route('workspace.analytics.index', $shop) }}" class="font-medium text-brand-700 hover:underline">{{ __('Visitors and views') }}</a>
            <a href="{{ route('workspace.insights.metrics', $shop) }}" class="font-medium text-brand-700 hover:underline">{{ __('How each figure is calculated') }}</a>
        </p>

        <x-report-filter :action="route('workspace.insights.index', $shop)" :period="$period" :export="route('workspace.insights.export', [$shop, 'from' => $period->from->format('Y-m-d'), 'to' => $period->to->format('Y-m-d')])" />
        <x-provenance-note :provenance="$report['provenance']" />

        <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-tile :label="__('Recorded sales revenue')" :value="'BDT '.$money($m['sales_revenue'])" :hint="__(':count recorded sales', ['count' => $m['sales_count']])" />
            <x-metric-tile :label="__('Average sale value')" :value="$m['average_sale_value'] === null ? '—' : 'BDT '.$money($m['average_sale_value'])" :hint="__(':units units sold', ['units' => $m['units_sold']])" />
            <x-metric-tile :label="__('Orders placed')" :value="$m['orders_placed']" :hint="__(':completed completed, :reservations reservations', ['completed' => $m['orders_completed'], 'reservations' => $m['reservations_placed']])" />
            <x-metric-tile :label="__('Order completion rate')" :value="$m['order_completion_rate'] === null ? '—' : $m['order_completion_rate'].'%'" :hint="__('completed ÷ placed')" />
            <x-metric-tile :label="__('Returns and exchanges')" :value="$m['returns_completed']" :hint="'BDT '.$money($m['returns_value'])" />
            <x-metric-tile :label="__('Average rating')" :value="$m['rating_average'] === null ? '—' : $m['rating_average']" :hint="__(':count published reviews', ['count' => $m['reviews_published']])" />
            <x-metric-tile :label="__('Listings out of stock')" :value="$m['stock_out_listings']" :hint="__(':low with low stock', ['low' => $m['low_stock_listings']])" />
            <x-metric-tile :label="__('Promotion redemptions')" :value="$m['coupon_redemptions']" :hint="__('BDT :discount in discounts', ['discount' => $money($m['discount_given'])])" />
        </dl>

        <section class="mt-8" aria-labelledby="daily-heading">
            <h2 id="daily-heading" class="font-semibold">{{ __('Recorded sales per day') }}</h2>
            @if ($m['sales_count'] === 0)
                <p class="mt-2 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No sales were recorded in this period.') }}</p>
            @else
                <x-daily-bars :series="collect($report['daily_sales'])" :caption="__('Recorded sales per day')" :heading="__('Revenue (BDT)')" :decimals="2" />
            @endif

            <h3 class="mt-4 text-sm font-semibold">{{ __('Unusual sales days') }}</h3>
            @if (! $report['anomalies']['available'])
                <p class="mt-1 text-sm text-slate-600">{{ $report['anomalies']['reason'] }}</p>
            @elseif ($report['anomalies']['days'] === [])
                <p class="mt-1 text-sm text-slate-600">{{ __('No day stood out from what its weekday usually brings.') }}</p>
            @else
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($report['anomalies']['days'] as $a)
                        <li>{{ \Illuminate\Support\Carbon::parse($a['day'])->format('D d M') }}: BDT {{ $money($a['revenue']) }} {{ $a['direction'] === 'high' ? __('(higher than usual)') : __('(lower than usual)') }}, {{ __('a usual :weekday is BDT :expected', ['weekday' => \Illuminate\Support\Carbon::parse($a['day'])->format('l'), 'expected' => $money($a['expected'])]) }} <span class="text-slate-500">(z = {{ $a['z'] }})</span></li>
                    @endforeach
                </ul>
                <p class="mt-1 text-xs text-slate-500">{{ __('Found by comparing each day with the median of its weekday (modified z-score beyond ±3.5). A prompt to look, not an explanation.') }}</p>
            @endif
        </section>

        <section class="mt-8" aria-labelledby="products-heading">
            <h2 id="products-heading" class="font-semibold">{{ __('Product performance') }}</h2>
            <p class="text-sm text-slate-600">{{ __('ABC: A brings the first 80% of revenue, B the next 15%, C the rest. XYZ: X steady, Y varying, Z erratic weekly demand (needs two full weeks).') }}</p>
            @if ($report['products'] === [])
                <p class="mt-2 text-sm text-slate-600">{{ __('You have no active listings yet.') }}</p>
            @else
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Product performance') }}</caption>
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">{{ __('Product') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Revenue (BDT)') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Units') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Share') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Class') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('In stock') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Days of cover') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['products'] as $p)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $p['label'] }} @if ($p['slow_moving']) <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-900">{{ __('slow moving') }}</span> @endif</td>
                                    <td class="px-4 py-2">{{ $money($p['revenue']) }}</td>
                                    <td class="px-4 py-2">{{ $p['units'] }}</td>
                                    <td class="px-4 py-2">{{ $p['share'] }}%</td>
                                    <td class="px-4 py-2">{{ $p['abc'] }}{{ $p['xyz'] ?? '' }}</td>
                                    <td class="px-4 py-2">{{ $p['on_hand'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $p['days_of_cover'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="mt-8 grid gap-8 md:grid-cols-2">
            <section aria-labelledby="stock-heading">
                <h2 id="stock-heading" class="font-semibold">{{ __('Stock now') }}</h2>
                <p class="text-sm text-slate-600">{{ __('A snapshot of today, not of the period.') }} {{ __(':tracked listings track stock; :units units on hand.', ['tracked' => $report['inventory']['tracked'], 'units' => $m['stock_units_on_hand']]) }}</p>
                <h3 class="mt-2 text-sm font-medium">{{ __('Out of stock') }}</h3>
                @forelse ($report['inventory']['stock_out'] as $label) <p class="text-sm">{{ $label }}</p> @empty <p class="text-sm text-slate-600">{{ __('None.') }}</p> @endforelse
                <h3 class="mt-2 text-sm font-medium">{{ __('Low stock') }}</h3>
                @forelse ($report['inventory']['low'] as $row) <p class="text-sm">{{ $row['label'] }} · {{ $row['on_hand'] }} ({{ __('alert at :n', ['n' => $row['threshold']]) }})</p> @empty <p class="text-sm text-slate-600">{{ __('None.') }}</p> @endforelse
                <h3 class="mt-2 text-sm font-medium">{{ __('In stock but not sold in this period') }}</h3>
                @forelse ($report['inventory']['slow_moving'] as $label) <p class="text-sm">{{ $label }}</p> @empty <p class="text-sm text-slate-600">{{ __('None.') }}</p> @endforelse
            </section>

            <section aria-labelledby="orders-heading">
                <h2 id="orders-heading" class="font-semibold">{{ __('Orders and reservations by status') }}</h2>
                @forelse ($report['orders']['by_status'] as $status => $count)
                    <p class="mt-1 text-sm"><span class="font-medium">{{ str_replace('_', ' ', $status) }}</span> · {{ $count }}</p>
                @empty
                    <p class="mt-1 text-sm text-slate-600">{{ __('No orders were placed in this period.') }}</p>
                @endforelse
            </section>
        </div>

        <section class="mt-8" aria-labelledby="feedback-heading">
            <h2 id="feedback-heading" class="font-semibold">{{ __('Customer feedback') }}</h2>
            @if ($feedback['count'] === 0)
                <p class="mt-1 text-sm text-slate-600">{{ __('No reviews were published in this period.') }}</p>
            @else
                <p class="mt-1 text-sm">{{ __('Average :average from :count reviews.', ['average' => $feedback['average'], 'count' => $feedback['count']]) }}
                    @foreach ($feedback['distribution'] as $stars => $n) <span class="ml-2 text-slate-600">{{ $stars }}★ {{ $n }}</span> @endforeach
                </p>
            @endif
            @php($trend = $feedback['trend'])
            <p class="mt-1 text-sm">
                @if (! $trend['available'])
                    {{ __('Rating trend: not available. :reason', ['reason' => $trend['reason']]) }}
                @else
                    {{ __('Rating trend over the last 120 days: :slope stars per 30 days (p :p, :n reviews).', ['slope' => ($trend['slope_per_30_days'] > 0 ? '+' : '').$trend['slope_per_30_days'], 'p' => \App\Modules\AnalyticsReports\Services\Reporting\FeedbackInsights::formatP($trend['p_value']), 'n' => $trend['reviews']]) }}
                    {{ $trend['significant'] ? __('This is unlikely to be chance.') : __('This is within what chance could produce.') }}
                @endif
            </p>
            <p class="mt-1 text-sm text-slate-600">{{ __('Complaints sent to your shop in this period: :count (:open not yet closed).', ['count' => $feedback['support_complaints']['total'], 'open' => $feedback['support_complaints']['open']]) }}</p>
            @if ($feedback['terms']['available'])
                <div class="mt-2 grid gap-4 text-sm md:grid-cols-2">
                    <div><h3 class="font-medium">{{ __('Words that mark poor reviews') }}</h3>@forelse ($feedback['terms']['complaints'] as $t) <p>{{ $t['term'] }} <span class="text-slate-500">({{ $t['count_a'] }} vs {{ $t['count_b'] }})</span></p> @empty <p class="text-slate-600">{{ __('None stand out.') }}</p> @endforelse</div>
                    <div><h3 class="font-medium">{{ __('Words that mark good reviews') }}</h3>@forelse ($feedback['terms']['praise'] as $t) <p>{{ $t['term'] }} <span class="text-slate-500">({{ $t['count_b'] }} vs {{ $t['count_a'] }})</span></p> @empty <p class="text-slate-600">{{ __('None stand out.') }}</p> @endforelse</div>
                </div>
            @else
                <p class="mt-1 text-sm text-slate-600">{{ __('Words in reviews: not available. :reason', ['reason' => $feedback['terms']['reason']]) }}</p>
            @endif
        </section>

        <section class="mt-8" aria-labelledby="visitors-heading">
            <h2 id="visitors-heading" class="font-semibold">{{ __('Visitors and views (real visitors)') }}</h2>
            <p class="mt-1 text-sm">{{ __('Your shop page was opened :views times (:visitors visitor-days). A page view is not a visit to the mall.', ['views' => $report['visitors']['shop_views'], 'visitors' => $report['visitors']['shop_visitors']]) }} <a href="{{ route('workspace.analytics.index', $shop) }}" class="font-medium text-brand-700 hover:underline">{{ __('Details and view-to-order funnel') }}</a></p>
        </section>

        <section class="mt-8" aria-labelledby="campaign-heading">
            <h2 id="campaign-heading" class="font-semibold">{{ __('Promotion performance') }}</h2>
            @if ($report['campaigns'] === [])
                <p class="mt-1 text-sm text-slate-600">{{ __('No promotion was redeemed in this period.') }}</p>
            @else
                <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Promotion performance') }}</caption>
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-4 py-2">{{ __('Promotion') }}</th><th scope="col" class="px-4 py-2">{{ __('Redemptions') }}</th><th scope="col" class="px-4 py-2">{{ __('Discount (BDT)') }}</th><th scope="col" class="px-4 py-2">{{ __('Value of those orders (BDT)') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['campaigns'] as $c)
                                <tr><td class="px-4 py-2 font-medium">{{ $c['title'] }}</td><td class="px-4 py-2">{{ $c['redemptions'] }}</td><td class="px-4 py-2">{{ $money($c['discount']) }}</td><td class="px-4 py-2">{{ $money($c['order_value']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ __('Orders placed with a promotion; they count as sales only once collected and recorded.') }}</p>
            @endif
        </section>
    </div>
</x-layouts.customer>
