<x-layouts.customer :mall="$mall">
    <section class="bg-linear-to-br from-brand-700 to-brand-900 text-white">
        <div class="mx-auto max-w-6xl px-4 py-10">
            <p class="text-sm text-white/80">{{ $mall->address }}, {{ $mall->city }}</p>
            <h1 class="mt-1 text-3xl font-semibold">{{ __('Welcome to :mall', ['mall' => $mall->name]) }}</h1>
            <p class="mt-2 text-white/90">{{ __('Find shops and products, then get directions inside the mall.') }}</p>
            <form method="GET" action="{{ route('shops.index', ['publicMall' => $mall->slug]) }}" class="mt-6 flex max-w-xl gap-2" role="search">
                <label for="mall-q" class="sr-only">{{ __('Search shops') }}</label>
                <input id="mall-q" name="q" type="search" maxlength="100" placeholder="{{ __('Find a shop, e.g. coffee') }}" class="min-w-0 flex-1 rounded-lg bg-white px-4 py-2.5 text-slate-900 ring-1 ring-white/40">
                <button type="submit" class="rounded-lg bg-white px-4 py-2.5 font-medium text-brand-700 hover:bg-brand-50">{{ __('Search') }}</button>
            </form>
            <div class="mt-6 flex flex-wrap gap-3 text-sm">
                <a href="{{ route('shops.index', ['publicMall' => $mall->slug]) }}" class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-4 py-2 font-medium hover:bg-white/25">
                    <x-icon name="shops" class="size-4" />{{ __('All shops') }}
                </a>
                <a href="{{ route('shops.index', ['publicMall' => $mall->slug, 'open_now' => 1]) }}" class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-4 py-2 font-medium hover:bg-white/25">
                    <x-icon name="clock" class="size-4" />{{ __(':open of :total shops open now', ['open' => $openShopCount, 'total' => $shopCount]) }}
                </a>
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-10 lg:grid-cols-3">
        <section class="lg:col-span-2" aria-labelledby="categories-heading">
            <h2 id="categories-heading" class="text-lg font-semibold">{{ __('Browse by category') }}</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('shops.index', ['publicMall' => $mall->slug, 'category' => $category->slug]) }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 hover:border-brand-500">
                            <span class="font-medium">{{ __($category->name) }}</span>
                            <span class="rounded-full bg-slate-100 px-2 text-xs text-slate-600">{{ $category->shops_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="floors-heading">
            <h2 id="floors-heading" class="text-lg font-semibold">{{ __('Floors') }}</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($floors as $floor)
                    <li>
                        <a href="{{ route('shops.index', ['publicMall' => $mall->slug, 'floor' => $floor->code]) }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 hover:border-brand-500">
                            <span class="grid size-9 place-items-center rounded-lg bg-brand-50 font-semibold text-brand-700">{{ $floor->code }}</span>
                            <span class="font-medium">{{ __($floor->name) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.customer>
