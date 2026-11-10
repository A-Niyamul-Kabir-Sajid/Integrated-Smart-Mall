<x-layouts.customer :title="__('Catalogue').' · '.$shop->name">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">{{ __('Shop workspace') }}</p>
                <h1 class="text-2xl font-semibold">{{ $shop->name }} · {{ __('Catalogue') }}</h1>
            </div>
            <div class="flex gap-2">
                @if (Route::has('workspace.reviews.index'))
                    <a href="{{ route('workspace.reviews.index', $shop) }}" class="rounded-lg border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50">{{ __('Reviews') }}</a>
                @endif
                @if (Route::has('workspace.catalogue.import.create'))
                    <a href="{{ route('workspace.catalogue.import.create', $shop) }}" class="rounded-lg border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50">{{ __('Import CSV') }}</a>
                @endif
                <a href="{{ route('workspace.catalogue.create', $shop) }}" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">{{ __('Add product') }}</a>
            </div>
        </div>

        <nav class="mt-6 flex gap-2 text-sm" aria-label="{{ __('Listing status') }}">
            @foreach (['active' => __('Active'), 'archived' => __('Archived')] as $value => $label)
                <x-nav-link :href="route('workspace.catalogue.index', [$shop, 'status' => $value])" :active="$status === $value">{{ $label }}</x-nav-link>
            @endforeach
        </nav>

        @if ($listings->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No listings here yet.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('SKU') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Product') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ __('Price') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Availability') }}</th>
                            <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($listings as $listing)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs">{{ $listing->seller_sku }}</td>
                                <td class="px-4 py-2">{{ $listing->variant->product->name }} <span class="text-slate-500">· {{ $listing->variant->label }}</span></td>
                                <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $listing->price_amount, 2) }}</td>
                                <td class="px-4 py-2">{{ __(ucfirst($listing->availability_status)) }}</td>
                                <td class="px-4 py-2 text-right"><a href="{{ route('workspace.catalogue.edit', [$shop, $listing]) }}" class="font-medium text-brand-700 hover:underline">{{ __('Edit') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $listings->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
