@php
    $statusLabels = ['published' => __('Published'), 'pending' => __('Waiting for approval'), 'hidden' => __('Hidden by a moderator'), 'withdrawn' => __('Withdrawn')];
@endphp

<x-layouts.customer :title="__('Your reviews')">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <a href="{{ route('account.show') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your account') }}</a>
        <h1 class="mt-3 text-2xl font-semibold">{{ __('Your reviews') }}</h1>

        @if ($reviews->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('You have not written any reviews yet.') }}</p>
        @else
            <div class="mt-6 space-y-4">
                @foreach ($reviews as $review)
                    @php
                        $mallSlug = $review->shop->mall->slug;
                        $target = $review->isShopReview() ? $review->shop->name : $review->listing->variant->product->name.' · '.$review->listing->variant->label.' ('.$review->shop->name.')';
                        $url = $review->isShopReview()
                            ? route('shops.reviews.index', ['publicMall' => $mallSlug, 'publicShop' => $review->shop->slug])
                            : route('listings.reviews.index', ['publicMall' => $mallSlug, 'publicListing' => $review->shop_product_id]);
                    @endphp
                    <x-review-card :review="$review" :shop-name="$review->shop->name">
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 text-sm">
                            <div>
                                <a href="{{ $url }}" class="font-medium text-brand-700 hover:underline">{{ $target }}</a>
                                <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $statusLabels[$review->status->value] }}</span>
                            </div>
                            @if (in_array($review->status->value, ['published', 'pending'], true))
                                <form method="POST" action="{{ route('reviews.withdraw', $review) }}" onsubmit="return confirm('{{ __('Withdraw this review? It will no longer be shown.') }}')">
                                    @csrf
                                    <button type="submit" class="font-medium text-rose-700 hover:underline">{{ __('Withdraw') }}</button>
                                </form>
                            @endif
                        </div>
                    </x-review-card>
                @endforeach
            </div>
            <div class="mt-4">{{ $reviews->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
