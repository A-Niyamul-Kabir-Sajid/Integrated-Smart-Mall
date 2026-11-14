<x-layouts.customer :title="__('Search demand')" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Search demand') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('What visitors type into the shop and product search, and what they could not find. Only the first page of a search is counted. Terms that look like an email address or a phone number are never stored. This measures interest in searching, not visits to the mall.') }}</p>
        <p class="mt-1 text-sm"><a href="{{ route('mall-admin.analytics.views', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Page views') }}</a></p>

        <x-analytics-filter :action="route('mall-admin.analytics.search', $mall)" :period="$period" :label="$label" :kind="$kind" with-kind />

        <dl class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs uppercase text-slate-500">{{ __('Searches') }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $report['searches'] }}</dd></div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs uppercase text-slate-500">{{ __('With no result') }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $report['zero_results'] }}</dd></div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><dt class="text-xs uppercase text-slate-500">{{ __('Text not stored (looked personal)') }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $report['masked'] }}</dd></div>
        </dl>

        @if ($report['searches'] === 0)
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No searches were recorded in this period.') }}</p>
        @else
            <div class="mt-8 grid gap-8 md:grid-cols-2">
                <section aria-labelledby="unmet-heading">
                    <h2 id="unmet-heading" class="font-semibold">{{ __('Searched but not found') }}</h2>
                    <p class="text-sm text-slate-600">{{ __('Terms that returned no result: products or shops visitors want that the mall does not offer.') }}</p>
                    @if ($report['unmet']->isEmpty())
                        <p class="mt-2 text-sm text-slate-600">{{ __('Every search found something.') }}</p>
                    @else
                        <table class="mt-2 w-full rounded-xl border border-slate-200 bg-white text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-3 py-2">{{ __('Term') }}</th><th scope="col" class="px-3 py-2">{{ __('No result') }}</th><th scope="col" class="px-3 py-2">{{ __('All searches') }}</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($report['unmet'] as $row)
                                    <tr><td class="px-3 py-1.5 font-medium">{{ $row['term'] }}</td><td class="px-3 py-1.5">{{ $row['zero_results'] }}</td><td class="px-3 py-1.5">{{ $row['searches'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>

                <section aria-labelledby="top-heading">
                    <h2 id="top-heading" class="font-semibold">{{ __('Most searched terms') }}</h2>
                    <table class="mt-2 w-full rounded-xl border border-slate-200 bg-white text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th scope="col" class="px-3 py-2">{{ __('Term') }}</th><th scope="col" class="px-3 py-2">{{ __('Searches') }}</th><th scope="col" class="px-3 py-2">{{ __('No result') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['top_terms'] as $row)
                                <tr><td class="px-3 py-1.5 font-medium">{{ $row['term'] }}</td><td class="px-3 py-1.5">{{ $row['searches'] }}</td><td class="px-3 py-1.5">{{ $row['zero_results'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                <section aria-labelledby="category-heading">
                    <h2 id="category-heading" class="font-semibold">{{ __('Searches by category filter') }}</h2>
                    @forelse ($report['categories'] as $row)
                        <p class="mt-1 text-sm"><span class="font-medium">{{ $row['key'] }}</span> · {{ $row['searches'] }}</p>
                    @empty
                        <p class="mt-1 text-sm text-slate-600">{{ __('No searches used a category filter.') }}</p>
                    @endforelse
                </section>

                <section aria-labelledby="floor-heading">
                    <h2 id="floor-heading" class="font-semibold">{{ __('Searches by floor filter') }}</h2>
                    @forelse ($report['floors'] as $row)
                        <p class="mt-1 text-sm"><span class="font-medium">{{ $row['key'] }}</span> · {{ $row['searches'] }}</p>
                    @empty
                        <p class="mt-1 text-sm text-slate-600">{{ __('No searches used a floor filter.') }}</p>
                    @endforelse
                </section>
            </div>
        @endif
    </div>
</x-layouts.customer>
