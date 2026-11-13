<x-layouts.customer :title="__('Stock').' · '.$shop->name">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Stock') }}</h1>
            <a href="{{ route('workspace.catalogue.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Catalogue') }}</a>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ __('Stock follows what is recorded here and in sales: deliveries and counts change it, a recorded sale takes goods out. Listings without a count are not tracked.') }}</p>

        <nav class="mt-5 flex gap-2 text-sm" aria-label="{{ __('Stock filter') }}">
            <x-nav-link :href="route('workspace.stock.index', $shop)" :active="! $lowOnly">{{ __('All listings') }}</x-nav-link>
            <x-nav-link :href="route('workspace.stock.index', [$shop, 'low' => 1])" :active="$lowOnly">{{ __('Low stock') }} ({{ $lowCount }})</x-nav-link>
        </nav>

        @if ($lowCount > 0 && ! $lowOnly)
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">{{ trans_choice(':count listing is at or below its low-stock level.|:count listings are at or below their low-stock level.', $lowCount, ['count' => $lowCount]) }}</p>
        @endif

        @if ($mismatches->isNotEmpty())
            <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ __('Reconciliation found :count listing(s) whose quantity does not match the ledger. Contact support.', ['count' => $mismatches->count()]) }}</p>
        @endif

        @if ($listings->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ $lowOnly ? __('Nothing is low on stock.') : __('No listings yet.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('SKU') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Product') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ __('On hand') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ __('Alert at') }}</th>
                            <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($listings as $listing)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs">{{ $listing->seller_sku }}</td>
                                <td class="px-4 py-2">{{ $listing->variant->product->name }} <span class="text-slate-500">· {{ $listing->variant->label }}</span></td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    @if ($listing->stock)
                                        {{ $listing->stock->on_hand }}
                                        @if ($listing->stock->isLow()) <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">{{ __('Low') }}</span> @endif
                                    @else
                                        <span class="text-slate-400">{{ __('Not tracked') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $listing->stock && $listing->stock->low_stock_threshold > 0 ? $listing->stock->low_stock_threshold : '—' }}</td>
                                <td class="px-4 py-2 text-right"><a href="{{ route('workspace.stock.show', [$shop, $listing]) }}" class="font-medium text-brand-700 hover:underline" aria-label="{{ __('Manage stock of :name', ['name' => $listing->variant->product->name.' '.$listing->variant->label]) }}">{{ __('Manage') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $listings->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
