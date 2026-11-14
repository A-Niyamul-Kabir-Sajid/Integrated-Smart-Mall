<x-layouts.customer :title="__('Visitors and views').' · '.$shop->name">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Visitors and views') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('How often your shop page and your products were opened, and how many of those views became orders and sales. Your own visits and your staff\'s are not counted. A page view is not a visit to the mall.') }}</p>

        <x-analytics-filter :action="route('workspace.analytics.index', $shop)" :period="$period" :label="$label" />

        <dl class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs uppercase text-slate-500">{{ __('Shop page views') }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $report['shop_views'] }}</dd></div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs uppercase text-slate-500">{{ __('Visitor-days') }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $report['shop_visitors'] }}</dd><dd class="text-xs text-slate-500">{{ __('Distinct visitors per day, added up over the days') }}</dd></div>
        </dl>

        <section class="mt-8" aria-labelledby="daily-heading">
            <h2 id="daily-heading" class="font-semibold">{{ __('Shop page views per day') }}</h2>
            @if ($report['shop_views'] === 0)
                <p class="mt-2 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No views of your shop page were recorded in this period.') }}</p>
            @else
                <x-daily-bars :series="$report['daily']" :caption="__('Shop page views per day')" />
            @endif
        </section>

        <section class="mt-8" aria-labelledby="funnel-heading">
            <h2 id="funnel-heading" class="font-semibold">{{ __('From view to order to sale, per product') }}</h2>
            <p class="text-sm text-slate-600">{{ __('Product views are shared with every other shop that sells the same product. Sales include counter sales that did not start online.') }}</p>
            @unless ($report['funnel_available'])
                <p class="mt-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">{{ __('Demonstration views are not matched with real orders and sales, so those columns stay empty.') }}</p>
            @endunless
            @if ($report['funnel']->isEmpty())
                <p class="mt-2 text-sm text-slate-600">{{ __('You have no active listings yet.') }}</p>
            @else
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">{{ __('Product') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Product views') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Orders') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Recorded sales') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Orders per 100 views') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['funnel'] as $row)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $row['label'] }}</td>
                                    <td class="px-4 py-2">{{ $row['product_views'] }}</td>
                                    <td class="px-4 py-2">{{ $report['funnel_available'] ? $row['orders'] : '—' }}</td>
                                    <td class="px-4 py-2">{{ $report['funnel_available'] ? $row['sales'] : '—' }}</td>
                                    <td class="px-4 py-2">{{ $row['conversion'] === null ? '—' : $row['conversion'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts.customer>
