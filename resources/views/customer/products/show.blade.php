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
            @error('listings') <p class="mt-3 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror
            @error('listings.*') <p class="mt-3 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror
            <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($offers as $offer)
                    @php($unit = $offer->shop->units->where('status', '!=', 'archived')->first())
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3">
                        <input type="checkbox" name="listings[]" value="{{ $offer->shop_product_id }}" @checked(in_array($offer->shop_product_id, (array) old('listings', []), true)) class="size-4 accent-brand-600" aria-label="{{ __('Compare :shop', ['shop' => $offer->shop->name]) }}">
                        <div class="min-w-40 flex-1">
                            <a href="{{ route('shops.show', ['publicMall' => $mall->slug, 'publicShop' => $offer->shop->slug]) }}" class="font-medium text-brand-700 hover:underline">{{ $offer->shop->name }}</a>
                            <p class="text-xs text-slate-500">{{ $offer->variant->label }}@if ($unit) · {{ __($unit->floor->name) }} · {{ $unit->unit_code }}@endif</p>
                        </div>
                        @if (isset($listingRatings[$offer->shop_product_id]))
                            @php($rating = $listingRatings[$offer->shop_product_id])
                            <a href="{{ route('listings.reviews.index', ['publicMall' => $mall->slug, 'publicListing' => $offer->shop_product_id]) }}" class="flex items-center gap-1 text-xs text-slate-600 hover:underline">
                                <x-rating-stars :rating="$rating->averageRating" />
                                <span>{{ $rating->reviewCount > 0 ? '('.$rating->reviewCount.')' : __('Reviews') }}</span>
                            </a>
                        @endif
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-50 text-emerald-700' => $offer->availability_status === 'available',
                            'bg-rose-50 text-rose-700' => $offer->availability_status === 'unavailable',
                            'bg-slate-100 text-slate-600' => $offer->availability_status === 'unknown',
                        ])>{{ ['available' => __('Available'), 'unavailable' => __('Out of stock'), 'unknown' => __('Availability unknown')][$offer->availability_status] }}</span>
                        <span class="w-24 text-right font-semibold tabular-nums">৳{{ number_format((float) $offer->price_amount, 2) }}</span>
                        @isset($favouritesEnabled)
                            @auth
                                @php($isSaved = in_array($offer->shop_product_id, $savedListingIds, true))
                                <button type="submit" form="favourite-{{ $offer->shop_product_id }}" aria-pressed="{{ $isSaved ? 'true' : 'false' }}" aria-label="{{ $isSaved ? __('Remove :shop offer from saved items', ['shop' => $offer->shop->name]) : __('Save :shop offer', ['shop' => $offer->shop->name]) }}" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-brand-700 hover:bg-brand-50">
                                    {{ $isSaved ? __('Saved ✓') : __('Save') }}
                                </button>
                            @endauth
                        @endisset
                    </li>
                @endforeach
            </ul>
        </form>
        @isset($favouritesEnabled)
            @auth
                {{-- Save forms live outside the comparison form; each button above targets its own form by id. --}}
                <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="add-to-list-heading" data-integration="shopping-lists">
                    <h2 id="add-to-list-heading" class="font-semibold">{{ __('Add to a shopping list') }}</h2>
                    @if ($shoppingLists->isEmpty())
                        <p class="mt-2 text-sm text-slate-600">{{ __('You have no shopping list for :mall yet.', ['mall' => $mall->name]) }}
                            <a href="{{ route('account.lists.index') }}" class="font-medium text-brand-700 hover:underline">{{ __('Create a shopping list') }}</a></p>
                    @else
                        {{-- One form; each button posts it to its own list with formaction. --}}
                        <form method="POST" action="{{ route('account.lists.items.store', $shoppingLists->first()) }}" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                            <div>
                                <label for="list-quantity" class="block text-xs font-medium text-slate-600">{{ __('Quantity') }}</label>
                                <input id="list-quantity" name="quantity" type="number" min="1" max="99" value="1" class="mt-1 w-20 rounded-lg px-2 py-1.5 ring-1 ring-slate-300">
                            </div>
                            @foreach ($shoppingLists as $shoppingList)
                                <button type="submit" formaction="{{ route('account.lists.items.store', $shoppingList) }}" class="rounded-lg border border-brand-600 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Add to ":list"', ['list' => $shoppingList->name]) }}</button>
                            @endforeach
                        </form>
                    @endif
                    @error('business') <p class="mt-3 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror
                </section>
                @foreach ($offers as $offer)
                    @php($isSaved = in_array($offer->shop_product_id, $savedListingIds, true))
                    <form id="favourite-{{ $offer->shop_product_id }}" method="POST" action="{{ route($isSaved ? 'favourites.listings.destroy' : 'favourites.listings.store', $offer->shop_product_id) }}" class="hidden" data-integration="favourites">
                        @csrf
                        @if ($isSaved) @method('DELETE') @endif
                    </form>
                @endforeach
            @else
                <p class="mt-3 text-sm"><a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline">{{ __('Sign in to save offers') }}</a></p>
            @endauth
        @endisset
        <p class="mt-3 text-xs text-slate-500">{{ __('Prices and availability are maintained by each shop and may change.') }}</p>
    </div>
</x-layouts.customer>
