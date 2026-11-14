<x-layouts.customer :title="__('Page views')" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Page views') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('How often shop pages and product pages were opened. A visitor is counted once per page in 30 minutes, staff and crawlers are left out, and visitors are counted per day only (never followed across days). A page view is not a visit to the mall.') }}</p>
        <p class="mt-1 text-sm"><a href="{{ route('mall-admin.analytics.search', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Search demand') }}</a></p>

        <x-analytics-filter :action="route('mall-admin.analytics.views', $mall)" :period="$period" :label="$label" />

        <dl class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs uppercase text-slate-500">{{ __('Shop page views') }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $report['shop_views'] }}</dd></div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs uppercase text-slate-500">{{ __('Visitor-days on shop pages') }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $report['shop_visitors'] }}</dd><dd class="text-xs text-slate-500">{{ __('Distinct visitors per day, added up over the days') }}</dd></div>
        </dl>

        @if ($report['shop_views'] === 0 && $report['products']->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No page views were recorded in this period.') }}</p>
        @else
            <section class="mt-8" aria-labelledby="daily-heading">
                <h2 id="daily-heading" class="font-semibold">{{ __('Shop page views per day') }}</h2>
                <x-daily-bars :series="$report['daily']" :caption="__('Shop page views per day')" />
            </section>

            <div class="mt-8 grid gap-8 md:grid-cols-2">
                <section aria-labelledby="shops-heading">
                    <h2 id="shops-heading" class="font-semibold">{{ __('Shops') }}</h2>
                    <table class="mt-2 w-full rounded-xl border border-slate-200 bg-white text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-3 py-2">{{ __('Shop') }}</th><th scope="col" class="px-3 py-2">{{ __('Views') }}</th><th scope="col" class="px-3 py-2">{{ __('Visitor-days') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['shops'] as $row)
                                <tr><td class="px-3 py-1.5 font-medium">{{ $row['name'] }}</td><td class="px-3 py-1.5">{{ $row['views'] }}</td><td class="px-3 py-1.5">{{ $row['visitors'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
                <section aria-labelledby="products-heading">
                    <h2 id="products-heading" class="font-semibold">{{ __('Products (top :count)', ['count' => \App\Modules\AnalyticsReports\Services\ActivityReportService::LIST_LIMIT]) }}</h2>
                    <table class="mt-2 w-full rounded-xl border border-slate-200 bg-white text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-3 py-2">{{ __('Product') }}</th><th scope="col" class="px-3 py-2">{{ __('Views') }}</th><th scope="col" class="px-3 py-2">{{ __('Visitor-days') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['products'] as $row)
                                <tr><td class="px-3 py-1.5 font-medium">{{ $row['name'] }}</td><td class="px-3 py-1.5">{{ $row['views'] }}</td><td class="px-3 py-1.5">{{ $row['visitors'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            </div>
        @endif
    </div>
</x-layouts.customer>
