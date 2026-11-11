<x-layouts.customer :title="__('Saved')">
    <div class="mx-auto max-w-3xl space-y-8 px-4 py-10">
        <div>
            <a href="{{ route('account.show') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your account') }}</a>
            <h1 class="mt-2 text-2xl font-semibold">{{ __('Saved') }}</h1>
            <p class="text-slate-600">{{ __('Shops and offers you saved. Only you can see this list.') }}</p>
        </div>

        <section aria-labelledby="saved-shops-heading">
            <h2 id="saved-shops-heading" class="text-lg font-semibold">{{ __('Shops') }}</h2>
            @if ($savedShops->isEmpty())
                <p class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white px-5 py-4 text-sm text-slate-600">{{ __('No saved shops yet. Use "Save shop" on a shop page.') }}</p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    @foreach ($savedShops as $favourite)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div>
                                @if ($favourite->is_available)
                                    <a href="{{ route('shops.show', ['publicMall' => $favourite->shop->mall->slug, 'publicShop' => $favourite->shop->slug]) }}" class="font-medium text-brand-700 hover:underline">{{ $favourite->shop->name }}</a>
                                @else
                                    <span class="font-medium text-slate-500">{{ $favourite->shop->name }}</span>
                                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ __('No longer available') }}</span>
                                @endif
                                <p class="text-xs text-slate-500">{{ $favourite->shop->mall->name }} · {{ __($favourite->shop->category->name) }}</p>
                            </div>
                            <form method="POST" action="{{ route('favourites.shops.destroy', $favourite->shop_id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-rose-700 hover:underline" aria-label="{{ __('Remove :name', ['name' => $favourite->shop->name]) }}">{{ __('Remove') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="saved-offers-heading">
            <h2 id="saved-offers-heading" class="text-lg font-semibold">{{ __('Offers') }}</h2>
            @if ($savedListings->isEmpty())
                <p class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white px-5 py-4 text-sm text-slate-600">{{ __('No saved offers yet. Use "Save" next to a shop on a product page.') }}</p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    @foreach ($savedListings as $favourite)
                        @php($listing = $favourite->listing)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div>
                                @if ($favourite->is_available)
                                    <a href="{{ route('products.show', ['publicMall' => $listing->shop->mall->slug, 'publicProduct' => $listing->variant->product_id]) }}" class="font-medium text-brand-700 hover:underline">{{ $listing->variant->product->name }}</a>
                                @else
                                    <span class="font-medium text-slate-500">{{ $listing->variant->product->name }}</span>
                                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ __('No longer available') }}</span>
                                @endif
                                <p class="text-xs text-slate-500">{{ $listing->variant->label }} · {{ $listing->shop->name }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                @if ($favourite->is_available)
                                    <span class="font-semibold tabular-nums">৳{{ number_format((float) $listing->price_amount, 2) }}</span>
                                @endif
                                <form method="POST" action="{{ route('favourites.listings.destroy', $favourite->shop_product_id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-rose-700 hover:underline" aria-label="{{ __('Remove :name', ['name' => $listing->variant->product->name]) }}">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.customer>
