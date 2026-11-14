@php
    $m = $report['metrics'];
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $flagText = ['rating_decline' => __('Falling ratings'), 'revenue_drop' => __('Sales drop'), 'unresolved_complaints' => __('Unresolved complaints'), 'complaint_outlier' => __('Many complaints')];
@endphp
<x-layouts.customer :title="__('Sales and performance')" :mall="$mall">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Sales and performance') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('How the shops of the mall are doing, what visitors want, and where service needs a look. Sales are completed, recorded transactions only. Nothing here measures how many people walked through the mall.') }}</p>
        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            <a href="{{ route('mall-admin.analytics.forecast', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Sales forecast (prototype)') }}</a>
            <a href="{{ route('mall-admin.analytics.reports.index', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Monthly reports') }}</a>
            <a href="{{ route('mall-admin.analytics.search', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Search demand') }}</a>
            <a href="{{ route('mall-admin.analytics.views', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Page views') }}</a>
            <a href="{{ route('mall-admin.analytics.metrics', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('How each figure is calculated') }}</a>
        </p>

        <x-report-filter :action="route('mall-admin.analytics.index', $mall)" :period="$period" :export="route('mall-admin.analytics.export', [$mall, 'from' => $period->from->format('Y-m-d'), 'to' => $period->to->format('Y-m-d')])" />
        <x-provenance-note :provenance="$report['provenance']" />

        <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-tile :label="__('Recorded sales revenue')" :value="'BDT '.$money($m['sales_revenue'])" :hint="__(':count recorded sales', ['count' => $m['sales_count']])" />
            <x-metric-tile :label="__('Shops with a recorded sale')" :value="$m['shops_with_sales'].' / '.$m['active_shops']" :hint="__('of the active shops')" />
            <x-metric-tile :label="__('Orders placed')" :value="$m['orders_placed']" :hint="__(':completed completed', ['completed' => $m['orders_completed']])" />
            <x-metric-tile :label="__('Average rating')" :value="$m['rating_average'] === null ? '—' : $m['rating_average']" :hint="__(':count published reviews', ['count' => $m['reviews_published']])" />
            <x-metric-tile :label="__('Complaints filed')" :value="$m['complaints_filed']" :hint="__(':open still open', ['open' => $m['complaints_unresolved']])" />
            <x-metric-tile :label="__('Median time to resolve')" :value="$m['complaint_resolution_hours'] === null ? '—' : $m['complaint_resolution_hours'].' h'" :hint="__(':count resolved', ['count' => $m['complaints_resolved']])" />
            <x-metric-tile :label="__('Searches (real visitors)')" :value="$report['search']['searches']" :hint="__(':none found nothing', ['none' => $report['search']['zero_results']])" />
            <x-metric-tile :label="__('Shop page views (real visitors)')" :value="$report['page_views']['shop_views']" :hint="__('not visits to the mall')" />
        </dl>
        <p class="mt-3 text-sm text-slate-600">{{ __('Coverage: :shops shops listed, :sales with a recorded sale, :reviews with a review (:five with five or more).', ['shops' => $report['coverage']['shops'], 'sales' => $report['coverage']['shops_with_sales'], 'reviews' => $report['coverage']['shops_with_reviews'], 'five' => $report['coverage']['shops_with_five_reviews']]) }}</p>

        <section class="mt-8" aria-labelledby="daily-heading">
            <h2 id="daily-heading" class="font-semibold">{{ __('Recorded sales per day, all shops') }}</h2>
            @if ($m['sales_count'] === 0)
                <p class="mt-2 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No sales were recorded in this period.') }}</p>
            @else
                <x-daily-bars :series="collect($report['daily_sales'])" :caption="__('Recorded sales per day, all shops')" :heading="__('Revenue (BDT)')" :decimals="2" />
            @endif
        </section>

        <section class="mt-8" aria-labelledby="tenants-heading">
            <h2 id="tenants-heading" class="font-semibold">{{ __('Shop comparison') }}</h2>
            <p class="text-sm text-slate-600">{{ __('The adjusted rating pulls shops with few reviews towards the mall average (mall mean :mean, prior weight :weight reviews :estimated), so two five-star reviews do not outrank two hundred good ones. Compare it with the raw rating and the review count beside it.', ['mean' => $report['rating_model']['grand_mean'], 'weight' => $report['rating_model']['prior_weight'], 'estimated' => $report['rating_model']['estimated'] ? '' : __('(default, too little data to estimate)')]) }}</p>
            @if ($report['tenants'] === [])
                <p class="mt-2 text-sm text-slate-600">{{ __('There are no shops to compare yet.') }}</p>
            @else
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Shop comparison') }}</caption>
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th scope="col" class="px-3 py-2">{{ __('Shop') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Revenue (BDT)') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Share') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Change') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Sales') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Orders') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Rating (n)') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Adjusted') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Views') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Complaints') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('Segment') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['tenants'] as $t)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $t['name'] }}<span class="block text-xs font-normal text-slate-500">{{ $t['category'] }}@if ($t['floor']) · {{ $t['floor'] }}@endif</span></td>
                                    <td class="px-3 py-2">{{ $money($t['revenue']) }}</td>
                                    <td class="px-3 py-2">{{ $t['revenue_share'] }}%</td>
                                    <td class="px-3 py-2">{{ $t['change_percent'] === null ? '—' : ($t['change_percent'] > 0 ? '+' : '').$t['change_percent'].'%' }}</td>
                                    <td class="px-3 py-2">{{ $t['sales'] }}</td>
                                    <td class="px-3 py-2">{{ $t['orders_completed'] }} / {{ $t['orders'] }}</td>
                                    <td class="px-3 py-2">{{ $t['rating'] ?? '—' }} ({{ $t['reviews'] }})</td>
                                    <td class="px-3 py-2">{{ $t['rating_adjusted'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $t['views'] }}</td>
                                    <td class="px-3 py-2">{{ $t['complaints'] }}</td>
                                    <td class="px-3 py-2">{{ $t['segment'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ __('Change compares revenue with the :days days before the period. Orders are collected / placed.', ['days' => $period->dayCount()]) }}</p>
            @endif

            <h3 class="mt-4 text-sm font-semibold">{{ __('Segments of shops with activity') }}</h3>
            @if (! $report['segments']['available'])
                <p class="mt-1 text-sm text-slate-600">{{ $report['segments']['reason'] }}</p>
            @else
                <p class="text-xs text-slate-500">{{ __('Shops grouped by k-means on sales, orders, rating and views (:k segments, silhouette :s; above 0.5 means well separated). A description of this period, not a ranking of merit.', ['k' => $report['segments']['k'], 's' => $report['segments']['silhouette']]) }}</p>
                <div class="mt-2 grid gap-3 md:grid-cols-3">
                    @foreach ($report['segments']['groups'] as $g)
                        <div class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                            <p class="font-medium">{{ $g['name'] }} <span class="text-slate-500">({{ count($g['shops']) }})</span></p>
                            <p class="text-slate-600">{{ __('Mean revenue BDT :revenue', ['revenue' => $money($g['mean_revenue'])]) }}@if ($g['mean_rating'] !== null) · {{ __('rating :rating', ['rating' => $g['mean_rating']]) }}@endif</p>
                            <p class="mt-1">{{ implode(', ', $g['shops']) }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="mt-8 grid gap-8 md:grid-cols-2">
            @foreach (['categories' => __('Demand by shop category'), 'floors' => __('Demand by floor')] as $key => $title)
                <section aria-labelledby="{{ $key }}-heading">
                    <h2 id="{{ $key }}-heading" class="font-semibold">{{ $title }}</h2>
                    <table class="mt-2 w-full rounded-xl border border-slate-200 bg-white text-sm">
                        <caption class="sr-only">{{ $title }}</caption>
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-3 py-2">{{ __('Name') }}</th><th scope="col" class="px-3 py-2">{{ __('Shops') }}</th><th scope="col" class="px-3 py-2">{{ __('Revenue (BDT)') }}</th><th scope="col" class="px-3 py-2">{{ __('Share') }}</th><th scope="col" class="px-3 py-2">{{ __('Views') }}</th><th scope="col" class="px-3 py-2">{{ __('Searches') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report[$key] as $row)
                                <tr><td class="px-3 py-1.5 font-medium">{{ $row['name'] }}</td><td class="px-3 py-1.5">{{ $row['shops'] }}</td><td class="px-3 py-1.5">{{ $money($row['revenue']) }}</td><td class="px-3 py-1.5">{{ $row['share'] }}%</td><td class="px-3 py-1.5">{{ $row['views'] }}</td><td class="px-3 py-1.5">{{ $row['searches'] }}</td></tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-2 text-slate-600">{{ __('Nothing to show.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-slate-500">{{ __('Searches count visitors who used that category or floor filter. Floor demand follows the shop\'s unit; it is sales and interest, not the number of people on the floor.') }}</p>

        <div class="mt-8 grid gap-8 md:grid-cols-2">
            <section aria-labelledby="complaints-heading">
                <h2 id="complaints-heading" class="font-semibold">{{ __('Complaints to management') }}</h2>
                @if ($report['complaints']['total'] === 0)
                    <p class="mt-1 text-sm text-slate-600">{{ __('No complaints were filed in this period.') }}</p>
                @else
                    <h3 class="mt-2 text-sm font-medium">{{ __('By category') }}</h3>
                    @foreach ($report['complaints']['by_category'] as $category => $count) <p class="text-sm">{{ str_replace('_', ' ', $category) }} · {{ $count }}</p> @endforeach
                    <h3 class="mt-2 text-sm font-medium">{{ __('By status now') }}</h3>
                    @foreach ($report['complaints']['by_status'] as $status => $count) <p class="text-sm">{{ str_replace('_', ' ', $status) }} · {{ $count }}</p> @endforeach
                @endif
            </section>

            <section aria-labelledby="flags-heading">
                <h2 id="flags-heading" class="font-semibold">{{ __('Service-quality flags') }}</h2>
                <p class="text-sm text-slate-600">{{ __('A flag is a prompt to look into a shop, with the evidence behind it. It is not a finding of fault.') }}</p>
                @forelse ($report['quality_flags'] as $flag)
                    <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm"><p class="font-medium text-amber-900">{{ $flag['shop'] }} · {{ $flagText[$flag['kind']] ?? $flag['kind'] }}</p><p class="text-amber-900">{{ $flag['evidence'] }}</p></div>
                @empty
                    <p class="mt-2 text-sm text-slate-600">{{ __('No shop met a flag rule in this period.') }}</p>
                @endforelse
            </section>
        </div>

        <section class="mt-8" aria-labelledby="feedback-heading">
            <h2 id="feedback-heading" class="font-semibold">{{ __('What reviews say across the mall') }}</h2>
            @if ($report['feedback']['terms']['available'])
                <div class="mt-2 grid gap-4 text-sm md:grid-cols-2">
                    <div><h3 class="font-medium">{{ __('Words that mark poor reviews') }}</h3>@forelse ($report['feedback']['terms']['complaints'] as $t) <p>{{ $t['term'] }} <span class="text-slate-500">({{ $t['count_a'] }} vs {{ $t['count_b'] }})</span></p> @empty <p class="text-slate-600">{{ __('None stand out.') }}</p> @endforelse</div>
                    <div><h3 class="font-medium">{{ __('Words that mark good reviews') }}</h3>@forelse ($report['feedback']['terms']['praise'] as $t) <p>{{ $t['term'] }} <span class="text-slate-500">({{ $t['count_b'] }} vs {{ $t['count_a'] }})</span></p> @empty <p class="text-slate-600">{{ __('None stand out.') }}</p> @endforelse</div>
                </div>
            @else
                <p class="mt-1 text-sm text-slate-600">{{ $report['feedback']['terms']['reason'] }}</p>
            @endif
        </section>

        <section class="mt-8" aria-labelledby="demand-heading">
            <h2 id="demand-heading" class="font-semibold">{{ __('Search and navigation demand') }}</h2>
            @forelse ($report['search']['unmet'] as $row)
                <p class="mt-1 text-sm">{{ __('Searched but not found:') }} <span class="font-medium">{{ $row['term'] }}</span> · {{ $row['zero_results'] }}</p>
            @empty
                <p class="mt-1 text-sm text-slate-600">{{ __('No unmet searches were recorded from real visitors in this period.') }}</p>
            @endforelse
            <p class="mt-2 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700" role="note">{{ __('Navigation requests: :reason', ['reason' => $report['navigation']['reason']]) }}</p>
        </section>
    </div>
</x-layouts.customer>
