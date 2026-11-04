<x-layouts.customer :title="__('Products')" :mall="$mall">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-2xl font-semibold">{{ __('Find a product') }}</h1>
        <p class="mt-1 text-slate-600">{{ __('See which shops in :mall sell it, and at what price.', ['mall' => $mall->name]) }}</p>

        <form method="GET" action="{{ route('products.index', ['publicMall' => $mall->slug]) }}" class="mt-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6" role="search">
            @if (filled($filters['shop'] ?? null))
                <input type="hidden" name="shop" value="{{ $filters['shop'] }}">
            @endif
            <div class="sm:col-span-2">
                <label for="product-q" class="text-xs font-medium text-slate-600">{{ __('Search products') }}</label>
                <input id="product-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('e.g. water, charger, T-shirt') }}" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="product-category" class="text-xs font-medium text-slate-600">{{ __('Category') }}</label>
                <select id="product-category" name="category" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected(($filters['category'] ?? null) === $category->slug)>{{ __($category->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="min-price" class="text-xs font-medium text-slate-600">{{ __('Min ৳') }}</label>
                    <input id="min-price" name="min_price" type="number" min="0" step="1" value="{{ $filters['min_price'] ?? '' }}" class="mt-1 w-full rounded-lg px-2 py-2 ring-1 ring-slate-300">
                </div>
                <div>
                    <label for="max-price" class="text-xs font-medium text-slate-600">{{ __('Max ৳') }}</label>
                    <input id="max-price" name="max_price" type="number" min="0" step="1" value="{{ $filters['max_price'] ?? '' }}" class="mt-1 w-full rounded-lg px-2 py-2 ring-1 ring-slate-300">
                </div>
            </div>
            <div>
                <label for="product-sort" class="text-xs font-medium text-slate-600">{{ __('Sort by') }}</label>
                <select id="product-sort" name="sort" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    @foreach (['name' => __('Name A–Z'), 'price_asc' => __('Lowest price'), 'price_desc' => __('Highest price')] as $sort => $label)
                        <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'name') === $sort)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col justify-end gap-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="available_only" value="1" @checked(request()->boolean('available_only')) class="size-4 accent-brand-600">{{ __('In stock only') }}</label>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">{{ __('Search') }}</button>
            </div>
        </form>

        @error('max_price') <p class="mt-3 text-sm text-rose-700">{{ $message }}</p> @enderror

        <p class="mt-6 text-sm text-slate-600" aria-live="polite">{{ trans_choice(':count product found|:count products found', $products->total(), ['count' => $products->total()]) }}</p>

        @if ($products->isEmpty())
            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No shop in this mall lists a matching product.') }}</div>
        @else
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <li class="relative flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-brand-500">
                        <p class="text-xs text-slate-500">{{ __($product->category->name) }}{{ $product->brand ? ' · '.$product->brand : '' }}</p>
                        <h2 class="font-semibold"><a href="{{ route('products.show', ['publicMall' => $mall->slug, 'publicProduct' => $product->product_id]) }}" class="after:absolute after:inset-0">{{ $product->name }}</a></h2>
                        <p class="mt-auto text-sm"><span class="font-semibold tabular-nums">{{ __('from ৳:price', ['price' => number_format((float) $product->min_price, 0)]) }}</span> <span class="text-slate-500">· {{ trans_choice(':count offer|:count offers', $product->offer_count, ['count' => $product->offer_count]) }}</span></p>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $products->links() }}</div>
        @endif
        <p class="mt-3 text-xs text-slate-500">{{ __('Prices and availability are maintained by each shop and may change.') }}</p>
    </div>
</x-layouts.customer>
