@php
    $isListing = $listing !== null;
    $targetName = $isListing ? $listing->variant->product->name.' · '.$listing->variant->label : $shop->name;
    $formAction = $isListing
        ? route('listings.reviews.store', ['publicMall' => $mall->slug, 'publicListing' => $listing->getKey()])
        : route('shops.reviews.store', ['publicMall' => $mall->slug, 'publicShop' => $shop->slug]);
    $backUrl = route('shops.show', ['publicMall' => $mall->slug, 'publicShop' => $shop->slug]);
@endphp

<x-layouts.customer :title="__('Reviews').' · '.$targetName" :mall="$mall">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ $backUrl }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>

        <header class="mt-4">
            <p class="text-sm text-slate-500">{{ $isListing ? __('Product reviews at :shop', ['shop' => $shop->name]) : __('Shop reviews') }}</p>
            <h1 class="text-2xl font-semibold">{{ $targetName }}</h1>
        </header>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-label="{{ __('Rating summary') }}">
            <x-rating-summary :summary="$summary" />
            @unless ($isListing)
                <p class="mt-4 text-xs text-slate-500">{{ __('This score is for the shop. Product reviews are rated separately on each product.') }}</p>
            @endunless
        </section>

        <section class="mt-6" aria-labelledby="write-heading">
            <h2 id="write-heading" class="text-lg font-semibold">{{ $own ? __('Your review') : __('Write a review') }}</h2>

            @guest
                <p class="mt-2 rounded-xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-700">
                    <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline">{{ __('Sign in') }}</a> {{ __('to write a review.') }}
                </p>
            @else
                @if ($isStaff)
                    <p class="mt-2 rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-700">{{ __('You work at this shop, so you cannot review it or its products.') }}</p>
                @elseif ($own?->status->value === 'hidden')
                    <p class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">{{ __('A moderator hid your review, so it cannot be changed.') }}</p>
                @elseif ($own && ! $ownCanEdit)
                    <p class="mt-2 rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-700">{{ __('You can no longer edit this review, but you can withdraw it from your reviews page.') }}</p>
                @else
                    @include('customer.reviews._form', ['action' => $formAction, 'own' => $own, 'isListing' => $isListing])
                @endif

                @if ($own && $isListing && ! $own->isVerified() && $own->status->isPublic())
                    <form method="POST" action="{{ route('reviews.verify', $own) }}" class="mt-4 flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-4">
                        @csrf
                        <div class="min-w-60 flex-1">
                            <label for="verify_purchase_code" class="block text-sm font-medium">{{ __('Bought it? Verify your purchase') }}</label>
                            <input id="verify_purchase_code" name="purchase_code" type="text" autocomplete="off" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 font-mono text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500" placeholder="ismv_…">
                        </div>
                        <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Verify') }}</button>
                    </form>
                @endif
            @endguest
        </section>

        <section class="mt-8" aria-labelledby="reviews-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="reviews-heading" class="text-lg font-semibold">{{ __('What customers say') }}</h2>
                @if ($isListing)
                    <nav class="flex gap-2 text-sm" aria-label="{{ __('Filter reviews') }}">
                        <x-nav-link :href="route('listings.reviews.index', ['publicMall' => $mall->slug, 'publicListing' => $listing->getKey()])" :active="! $verifiedOnly">{{ __('All') }}</x-nav-link>
                        <x-nav-link :href="route('listings.reviews.index', ['publicMall' => $mall->slug, 'publicListing' => $listing->getKey(), 'verified' => 1])" :active="$verifiedOnly">{{ __('Verified purchases') }}</x-nav-link>
                    </nav>
                @endif
            </div>

            @if ($reviews->isEmpty())
                <p class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No reviews yet. Be the first to share your experience.') }}</p>
            @else
                <div class="mt-3 space-y-4">
                    @foreach ($reviews as $review)
                        <x-review-card :review="$review" :shop-name="$shop->name" />
                    @endforeach
                </div>
                <div class="mt-4">{{ $reviews->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.customer>
