@php
    $weekdays = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')];
    $units = $shop->units->where('status', '!=', 'archived');
@endphp

<x-layouts.customer :title="$shop->name" :mall="$mall">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <a href="{{ route('shops.index', ['publicMall' => $mall->slug]) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('All shops') }}</a>

        <div class="mt-4 grid gap-6 lg:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-slate-500">{{ __($shop->category->name) }}</p>
                        <h1 class="text-2xl font-semibold">{{ $shop->name }}</h1>
                    </div>
                    <x-open-badge :open="$isOpenNow" :closed-permanently="$shop->status->value === 'closed'" />
                </div>

                @if ($shop->description)
                    <p class="mt-4 text-slate-700">{{ $shop->description }}</p>
                @endif

                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div class="flex gap-3">
                        <x-icon name="pin" class="mt-0.5 text-brand-600" />
                        <div>
                            <dt class="font-medium">{{ __('Location') }}</dt>
                            @forelse ($units as $unit)
                                <dd class="text-slate-600">{{ __($unit->floor->name) }} · {{ __('Unit :code', ['code' => $unit->unit_code]) }}</dd>
                            @empty
                                <dd class="text-slate-600">{{ __('Location not published') }}</dd>
                            @endforelse
                        </div>
                    </div>
                    @if ($shop->contact_phone)
                        <div class="flex gap-3">
                            <x-icon name="phone" class="mt-0.5 text-brand-600" />
                            <div>
                                <dt class="font-medium">{{ __('Contact') }}</dt>
                                <dd><a href="tel:{{ $shop->contact_phone }}" class="text-brand-700 hover:underline">{{ $shop->contact_phone }}</a></dd>
                            </div>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="hours-heading">
                <h2 id="hours-heading" class="flex items-center gap-2 font-semibold"><x-icon name="clock" class="text-brand-600" />{{ __('Opening hours') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('Times are in :zone.', ['zone' => $mall->timezone]) }}</p>
                <table class="mt-4 w-full text-sm">
                    <tbody>
                        @foreach ($weeklySchedule as $weekday => $intervals)
                            <tr @class(['font-semibold text-brand-700' => $weekday === $todayWeekday]) @if ($weekday === $todayWeekday) aria-current="date" @endif>
                                <th scope="row" class="py-1 pr-3 text-left font-[inherit]">{{ $weekdays[$weekday] }}</th>
                                <td class="py-1 text-right tabular-nums">{{ $intervals === [] ? __('Closed') : implode(', ', $intervals) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-dashed border-slate-300 bg-white p-6" aria-labelledby="products-heading" data-integration="products">
                <h2 id="products-heading" class="font-semibold">{{ __('Products at this shop') }}</h2>
                @if (Route::has('products.index'))
                    <a href="{{ route('products.index', ['publicMall' => $mall->slug, 'shop' => $shop->slug]) }}" class="mt-2 inline-block text-sm font-medium text-brand-700 hover:underline">{{ __("See this shop's products and prices") }}</a>
                @else
                    <p class="mt-2 text-sm text-slate-600">{{ __('Product listings and prices will appear here.') }}</p>
                @endif
            </section>
            <section class="rounded-xl border border-dashed border-slate-300 bg-white p-6" aria-labelledby="directions-heading" data-integration="navigation">
                <h2 id="directions-heading" class="flex items-center gap-2 font-semibold"><x-icon name="map" class="text-brand-600" />{{ __('Directions') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('Indoor directions to this shop will appear here.') }}</p>
            </section>
        </div>
    </div>
</x-layouts.customer>
