<x-layouts.customer :title="__('Compare')" :mall="$mall">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-2xl font-semibold">{{ __('Compare offers') }}</h1>

        @if ($listings->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('None of the selected offers is available in this mall any more.') }}</p>
        @else
            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <th scope="row" class="w-36 bg-slate-50 px-4 py-3 text-xs uppercase text-slate-500">{{ __('Product') }}</th>
                            @foreach ($listings as $listing)
                                <td class="px-4 py-3 font-semibold">{{ $listing->variant->product->name }}<span class="block text-xs font-normal text-slate-500">{{ $listing->variant->label }}</span></td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row" class="bg-slate-50 px-4 py-3 text-xs uppercase text-slate-500">{{ __('Price') }}</th>
                            @foreach ($listings as $listing)
                                <td @class(['px-4 py-3 tabular-nums', 'font-semibold text-emerald-700' => (float) $listing->price_amount === (float) $listings->min('price_amount')])>৳{{ number_format((float) $listing->price_amount, 2) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row" class="bg-slate-50 px-4 py-3 text-xs uppercase text-slate-500">{{ __('Shop') }}</th>
                            @foreach ($listings as $listing)
                                <td class="px-4 py-3"><a href="{{ route('shops.show', ['publicMall' => $mall->slug, 'publicShop' => $listing->shop->slug]) }}" class="text-brand-700 hover:underline">{{ $listing->shop->name }}</a></td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row" class="bg-slate-50 px-4 py-3 text-xs uppercase text-slate-500">{{ __('Location') }}</th>
                            @foreach ($listings as $listing)
                                @php($unit = $listing->shop->units->where('status', '!=', 'archived')->first())
                                <td class="px-4 py-3">{{ $unit ? __($unit->floor->name).' · '.$unit->unit_code : __('Location not published') }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row" class="bg-slate-50 px-4 py-3 text-xs uppercase text-slate-500">{{ __('Availability') }}</th>
                            @foreach ($listings as $listing)
                                <td class="px-4 py-3">{{ ['available' => __('Available'), 'unavailable' => __('Out of stock'), 'unknown' => __('Availability unknown')][$listing->availability_status] }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row" class="bg-slate-50 px-4 py-3 text-xs uppercase text-slate-500">{{ __('Details') }}</th>
                            @foreach ($listings as $listing)
                                <td class="px-4 py-3 text-slate-600">{{ collect($listing->variant->attributes_json)->map(fn ($value, $key) => ucfirst($key).': '.$value)->join(', ') ?: '—' }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
        <p class="mt-3 text-xs text-slate-500">{{ __('Prices and availability are maintained by each shop and may change.') }}</p>
    </div>
</x-layouts.customer>
