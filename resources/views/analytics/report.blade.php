@php
    $metrics = $payload['dashboard']['metrics'];
    $previous = $payload['previous']['metrics'];
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $monthName = \Illuminate\Support\Carbon::parse($payload['month'].'-01')->format('F Y');
    $previousName = \Illuminate\Support\Carbon::parse($payload['previous']['month'].'-01')->format('F Y');
    $change = function ($now, $before): string {
        if ($now === null || $before === null || (float) $before === 0.0) { return '—'; }
        $percent = ((float) $now - (float) $before) / (float) $before * 100;
        return ($percent > 0 ? '+' : '').number_format($percent, 1).'%';
    };
@endphp
<x-layouts.customer :title="__('Monthly report :month', ['month' => $monthName]).' · '.$title">
    <div class="mx-auto max-w-4xl px-4 py-8 print:max-w-none print:px-0">
        <a href="{{ $back }}" class="text-sm font-medium text-brand-700 hover:underline print:hidden">&larr; {{ __('All reports') }}</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">{{ __('Monthly report: :month', ['month' => $monthName]) }}</h1>
                <p class="text-sm text-slate-600">{{ $payload['subject']['name'] }} · {{ __('period :from to :to (:zone)', ['from' => $payload['period']['from'], 'to' => $payload['period']['to'], 'zone' => $payload['period']['timezone']]) }} · {{ __('data read :at', ['at' => $report->source_cutoff_at->format('d M Y H:i')]) }} · {{ __('catalogue version :v', ['v' => $payload['metric_version']]) }}</p>
            </div>
            <p class="flex gap-2 print:hidden">
                <a href="{{ $csvRoute }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Download CSV') }}</a>
                <button type="button" onclick="window.print()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Print or save as PDF') }}</button>
            </p>
        </div>

        @if ($payload['partial'])
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">{{ __('This month is not over. The figures run up to :to; generate the report again after the month ends.', ['to' => $payload['period']['to']]) }}</p>
        @endif
        <x-provenance-note :provenance="$payload['dashboard']['provenance']" />

        <section class="mt-6" aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="font-semibold">{{ __('Summary') }}</h2>
            <p class="text-xs text-slate-500">{{ __('Each sentence is written from the figures below and names the metrics it rests on.') }}</p>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($payload['narrative'] as $line)
                    <li>{{ $line['text'] }} <span class="font-mono text-xs text-slate-400">[{{ implode(', ', $line['metrics']) }}]</span></li>
                @endforeach
            </ul>
        </section>

        <section class="mt-8" aria-labelledby="figures-heading">
            <h2 id="figures-heading" class="font-semibold">{{ __('Figures') }}</h2>
            <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ __('Figures of the month and the month before') }}</caption>
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-4 py-2">{{ __('Metric') }}</th><th scope="col" class="px-4 py-2">{{ $monthName }}</th><th scope="col" class="px-4 py-2">{{ $previousName }}</th><th scope="col" class="px-4 py-2">{{ __('Change') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($metrics as $code => $value)
                            @php($metric = \App\Modules\AnalyticsReports\Enums\ReportMetric::from($code))
                            <tr>
                                <td class="px-4 py-1.5">{{ $metric->label() }} <span class="text-xs text-slate-400">({{ $metric->unit() }})</span></td>
                                <td class="px-4 py-1.5 font-medium">{{ $metric->unit() === 'BDT' ? $money($value) : ($value ?? '—') }}</td>
                                <td class="px-4 py-1.5">{{ $metric->unit() === 'BDT' ? $money($previous[$code] ?? null) : ($previous[$code] ?? '—') }}</td>
                                <td class="px-4 py-1.5">{{ in_array($code, ['stock_units_on_hand', 'stock_out_listings', 'low_stock_listings', 'active_shops', 'navigation_requests'], true) ? '—' : $change($value, $previous[$code] ?? null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ __('Stock figures and the number of active shops are snapshots from when the report was generated and are not compared.') }}</p>
        </section>

        <section class="mt-8" aria-labelledby="daily-heading">
            <h2 id="daily-heading" class="font-semibold">{{ __('Recorded sales per day') }}</h2>
            @if ($metrics['sales_count'] === 0)
                <p class="mt-2 text-sm text-slate-600">{{ __('No sales were recorded in this month.') }}</p>
            @else
                <x-daily-bars :series="collect($payload['dashboard']['daily_sales'])" :caption="__('Recorded sales per day')" :heading="__('Revenue (BDT)')" :decimals="2" />
            @endif
        </section>

        @if ($payload['scope'] === 'shop')
            <section class="mt-8" aria-labelledby="products-heading">
                <h2 id="products-heading" class="font-semibold">{{ __('Top products') }}</h2>
                <table class="mt-2 w-full rounded-xl border border-slate-200 bg-white text-sm">
                    <caption class="sr-only">{{ __('Top products') }}</caption>
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-3 py-2">{{ __('Product') }}</th><th scope="col" class="px-3 py-2">{{ __('Revenue (BDT)') }}</th><th scope="col" class="px-3 py-2">{{ __('Units') }}</th><th scope="col" class="px-3 py-2">{{ __('Class') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach (array_slice($payload['dashboard']['products'], 0, 10) as $p)
                            <tr><td class="px-3 py-1.5 font-medium">{{ $p['label'] }}</td><td class="px-3 py-1.5">{{ $money($p['revenue']) }}</td><td class="px-3 py-1.5">{{ $p['units'] }}</td><td class="px-3 py-1.5">{{ $p['abc'] }}{{ $p['xyz'] ?? '' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @else
            <section class="mt-8" aria-labelledby="tenants-heading">
                <h2 id="tenants-heading" class="font-semibold">{{ __('Shops') }}</h2>
                <table class="mt-2 w-full rounded-xl border border-slate-200 bg-white text-sm">
                    <caption class="sr-only">{{ __('Shops') }}</caption>
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-3 py-2">{{ __('Shop') }}</th><th scope="col" class="px-3 py-2">{{ __('Revenue (BDT)') }}</th><th scope="col" class="px-3 py-2">{{ __('Share') }}</th><th scope="col" class="px-3 py-2">{{ __('Sales') }}</th><th scope="col" class="px-3 py-2">{{ __('Rating (n)') }}</th><th scope="col" class="px-3 py-2">{{ __('Complaints') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payload['dashboard']['tenants'] as $t)
                            <tr><td class="px-3 py-1.5 font-medium">{{ $t['name'] }}</td><td class="px-3 py-1.5">{{ $money($t['revenue']) }}</td><td class="px-3 py-1.5">{{ $t['revenue_share'] }}%</td><td class="px-3 py-1.5">{{ $t['sales'] }}</td><td class="px-3 py-1.5">{{ $t['rating'] ?? '—' }} ({{ $t['reviews'] }})</td><td class="px-3 py-1.5">{{ $t['complaints'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
            @if ($payload['dashboard']['quality_flags'] !== [])
                <section class="mt-8" aria-labelledby="flags-heading">
                    <h2 id="flags-heading" class="font-semibold">{{ __('Service-quality flags') }}</h2>
                    @foreach ($payload['dashboard']['quality_flags'] as $flag) <p class="mt-1 text-sm"><span class="font-medium">{{ $flag['shop'] }}</span>: {{ $flag['evidence'] }}</p> @endforeach
                </section>
            @endif
        @endif

        <p class="mt-8 text-xs text-slate-500">{{ __('Every figure is defined in "How each figure is calculated". Navigation requests measure interest, not visits; sales are recorded transactions, not page views.') }}</p>
    </div>
</x-layouts.customer>
