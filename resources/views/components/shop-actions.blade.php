@props(['shop', 'mall'])

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
