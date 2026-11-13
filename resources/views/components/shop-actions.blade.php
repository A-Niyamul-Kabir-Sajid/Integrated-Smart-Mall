@props(['shop', 'mall'])

@inject('promotionService', 'App\Modules\ShopOwnerOperations\Services\PromotionService')
@php($offers = $shop->status->value === 'active' ? $promotionService->publicOffers($shop) : collect())

@if ($offers->isNotEmpty())
    <section class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5" aria-labelledby="offers-heading" data-integration="promotions">
        <h2 id="offers-heading" class="font-semibold">{{ __('Offers') }}</h2>
        <ul class="mt-2 space-y-2 text-sm">
            @foreach ($offers as $offer)
                <li><span class="font-medium">{{ $offer->title }}</span>: {{ $offer->discount_type->value === 'percent' ? rtrim(rtrim((string) $offer->discount_value, '0'), '.').'% '.__('off') : '৳'.number_format((float) $offer->discount_value, 2).' '.__('off') }}@if ((float) $offer->min_subtotal > 0) {{ __('on orders of ৳:amount or more', ['amount' => number_format((float) $offer->min_subtotal, 2)]) }}@endif · {{ __('until :date', ['date' => $offer->ends_at->timezone($mall->timezone)->format('d M Y')]) }}@if ($offer->description) <span class="block text-slate-700">{{ $offer->description }}</span>@endif</li>
            @endforeach
        </ul>
    </section>
@endif

@if ($shop->status->value === 'active' && Route::has('orders.create'))
    <section class="mt-6 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-5" aria-label="{{ __('Order from this shop') }}" data-integration="orders">
        @auth
            <a href="{{ route('orders.create', ['publicMall' => $mall->slug, 'publicShop' => $shop->slug]) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Order from this shop') }}</a>
        @else
            <a href="{{ route('login') }}" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Sign in to order') }}</a>
        @endauth
        <p class="text-sm text-slate-600">{{ __('Reserve goods or order ahead and collect them at the shop.') }}</p>
    </section>
@endif
