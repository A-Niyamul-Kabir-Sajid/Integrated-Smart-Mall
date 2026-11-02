<x-layouts.customer :title="__('Shops')" :mall="$mall">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-2xl font-semibold">{{ __('Shop directory') }}</h1>

        <form method="GET" action="{{ route('shops.index', ['publicMall' => $mall->slug]) }}" class="mt-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="shop-floor" class="text-xs font-medium text-slate-600">{{ __('Floor') }}</label>
                <select id="shop-floor" name="floor" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('All floors') }}</option>
                    @foreach ($floors as $floor)
                        <option value="{{ $floor->code }}" @selected(($filters['floor'] ?? null) === $floor->code)>{{ __($floor->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="shop-category" class="text-xs font-medium text-slate-600">{{ __('Category') }}</label>
                <select id="shop-category" name="category" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected(($filters['category'] ?? null) === $category->slug)>{{ __($category->name) }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-end gap-2 pb-2 text-sm">
                <input type="checkbox" name="open_now" value="1" @checked(request()->boolean('open_now')) class="size-4 accent-brand-600">
                {{ __('Open now') }}
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">{{ __('Filter') }}</button>
            </div>
        </form>

        <p class="mt-6 text-sm text-slate-600" aria-live="polite">{{ trans_choice(':count shop found|:count shops found', $shops->total(), ['count' => $shops->total()]) }}</p>

        @if ($shops->isEmpty())
            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                {{ __('No shops match these filters.') }}
                <a href="{{ route('shops.index', ['publicMall' => $mall->slug]) }}" class="font-medium text-brand-700 hover:underline">{{ __('Clear filters') }}</a>
            </div>
        @else
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($shops as $shop)
                    <x-shop-card :shop="$shop" :mall="$mall" />
                @endforeach
            </ul>
            <div class="mt-6">{{ $shops->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
