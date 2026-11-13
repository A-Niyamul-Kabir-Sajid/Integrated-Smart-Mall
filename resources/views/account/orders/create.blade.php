<x-layouts.customer :title="__('Order from :shop', ['shop' => $shop->name])" :mall="$mall">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('shops.show', ['publicMall' => $mall->slug, 'publicShop' => $shop->slug]) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Order from :shop', ['shop' => $shop->name]) }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Choose what you need and when you will collect it. You pay at the shop when you pick it up; nothing is charged online.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if ($listings->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('This shop has nothing available to order right now.') }}</p>
        @else
            <form method="POST" action="{{ route('orders.store', ['publicMall' => $mall->slug, 'publicShop' => $shop->slug]) }}" class="mt-6 space-y-6">
                @csrf

                <fieldset>
                    <legend class="font-semibold">{{ __('Products') }}</legend>
                    @error('quantities') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    <ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                        @foreach ($listings as $listing)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <label for="qty-{{ $listing->getKey() }}" class="min-w-48 flex-1">
                                    <span class="font-medium">{{ $listing->variant->product->name }}</span>
                                    <span class="text-sm text-slate-500">· {{ $listing->variant->label }}</span>
                                </label>
                                <span class="tabular-nums text-sm">৳{{ number_format((float) $listing->price_amount, 2) }}</span>
                                <input id="qty-{{ $listing->getKey() }}" type="number" name="quantities[{{ $listing->getKey() }}]" min="0" max="{{ \App\Modules\ShopOwnerOperations\Services\OrderService::MAX_LINE_QUANTITY }}" value="{{ old('quantities.'.$listing->getKey(), $preselected === $listing->getKey() ? 1 : 0) }}" class="w-20 rounded-lg px-2 py-1.5 text-right ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                            </li>
                        @endforeach
                    </ul>
                </fieldset>

                <fieldset class="space-y-2">
                    <legend class="font-semibold">{{ __('How do you want it?') }}</legend>
                    @foreach ($kinds as $kind)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="kind" value="{{ $kind->value }}" @checked(old('kind', 'click_and_collect') === $kind->value) class="accent-brand-600">
                            {{ $kind->label() }}
                        </label>
                    @endforeach
                    @error('kind') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form-field name="pickup_by" type="datetime-local" :label="__('Collect by')" required :hint="__('Between 30 minutes and 7 days from now, in the mall timezone (:zone).', ['zone' => $mall->timezone])" />
                </div>

                <div>
                    <label for="note" class="block text-sm font-medium">{{ __('Note for the shop (optional)') }}</label>
                    <textarea id="note" name="note" rows="2" maxlength="500" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('note') }}</textarea>
                    @error('note') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                @isset($couponEnabled)
                    <x-form-field name="coupon_code" :label="__('Coupon code (optional)')" maxlength="30" />
                @endisset

                <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Send order') }}</button>
            </form>
        @endif
    </div>
</x-layouts.customer>
