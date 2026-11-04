<x-layouts.customer :title="$product->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('products.index', ['publicMall' => $mall->slug]) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('All products') }}</a>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-6">
            <p class="text-sm text-slate-500">{{ __($product->category->name) }}{{ $product->brand ? ' · '.$product->brand : '' }}</p>
            <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
            @if ($product->description)
                <p class="mt-2 text-slate-700">{{ $product->description }}</p>
            @endif
            @if ($product->images->isNotEmpty())
                <div class="mt-4 flex gap-3 overflow-x-auto">
                    @foreach ($product->images as $image)
                        <img src="{{ Storage::disk('public')->url($image->object_key) }}" alt="{{ $image->alt_text }}" class="h-32 w-32 rounded-lg object-cover" loading="lazy">
                    @endforeach
                </div>
            @endif
        </div>

        <form method="GET" action="{{ route('products.compare', ['publicMall' => $mall->slug]) }}" class="mt-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ trans_choice('Sold by :count shop|Sold by :count shops', $offers->pluck('shop_id')->unique()->count(), ['count' => $offers->pluck('shop_id')->unique()->count()]) }}</h2>
                <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Compare selected (up to 4)') }}</button>
            </div>
            <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($offers as $offer)
                    @php($unit = $offer->shop->units->where('status', '!=', 'archived')->first())
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3">
                        <input type="checkbox" name="listings[]" value="{{ $offer->shop_product_id }}" class="size-4 accent-brand-600" aria-label="{{ __('Compare :shop', ['shop' => $offer->shop->name]) }}">
                        <div class="min-w-40 flex-1">
                            <a href="{{ route('shops.show', ['publicMall' => $mall->slug, 'publicShop' => $offer->shop->slug]) }}" class="font-medium text-brand-700 hover:underline">{{ $offer->shop->name }}</a>
                            <p class="text-xs text-slate-500">{{ $offer->variant->label }}@if ($unit) · {{ __($unit->floor->name) }} · {{ $unit->unit_code }}@endif</p>
                        </div>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-50 text-emerald-700' => $offer->availability_status === 'available',
                            'bg-rose-50 text-rose-700' => $offer->availability_status === 'unavailable',
                            'bg-slate-100 text-slate-600' => $offer->availability_status === 'unknown',
                        ])>{{ ['available' => __('Available'), 'unavailable' => __('Out of stock'), 'unknown' => __('Availability unknown')][$offer->availability_status] }}</span>
                        <span class="w-24 text-right font-semibold tabular-nums">৳{{ number_format((float) $offer->price_amount, 2) }}</span>
                    </li>
                @endforeach
            </ul>
        </form>
        <p class="mt-3 text-xs text-slate-500">{{ __('Prices and availability are maintained by each shop and may change.') }}</p>
    </div>
</x-layouts.customer>
