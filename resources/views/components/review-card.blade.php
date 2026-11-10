@props(['review', 'shopName' => null])

<article class="rounded-xl border border-slate-200 bg-white p-5" aria-label="{{ __('Review by :name', ['name' => $review->author?->display_name ?? __('a customer')]) }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <x-rating-stars :rating="$review->rating" />
            @if ($review->isVerified())
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Verified purchase') }}</span>
            @endif
        </div>
        <p class="text-xs text-slate-500">{{ $review->author?->display_name }} · {{ $review->created_at->timezone('Asia/Dhaka')->format('d M Y') }}</p>
    </div>

    @if ($review->title)
        <h3 class="mt-2 font-semibold">{{ $review->title }}</h3>
    @endif
    <p class="mt-1 whitespace-pre-line text-slate-700">{{ $review->body }}</p>

    @foreach ($review->replies as $reply)
        <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm">
            <p class="font-medium">{{ __('Reply from :shop', ['shop' => $shopName ?? __('the shop')]) }}</p>
            <p class="mt-1 whitespace-pre-line text-slate-700">{{ $reply->body }}</p>
        </div>
    @endforeach

    {{ $slot }}
</article>
