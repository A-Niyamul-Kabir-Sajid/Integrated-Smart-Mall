<x-layouts.customer :title="__('Reviews').' · '.$shop->name">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">{{ __('Shop workspace') }}</p>
                <h1 class="text-2xl font-semibold">{{ $shop->name }} · {{ __('Reviews') }}</h1>
            </div>
            <a href="{{ route('workspace.catalogue.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Catalogue') }}</a>
        </div>

        @if ($errors->has('business'))
            <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $errors->first('business') }}</p>
        @endif

        <nav class="mt-6 flex gap-2 text-sm" aria-label="{{ __('Filter reviews') }}">
            <x-nav-link :href="route('workspace.reviews.index', $shop)" :active="! $unanswered">{{ __('All') }}</x-nav-link>
            <x-nav-link :href="route('workspace.reviews.index', [$shop, 'unanswered' => 1])" :active="$unanswered">{{ __('Not answered yet') }}</x-nav-link>
        </nav>

        @if ($reviews->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No reviews here yet.') }}</p>
        @else
            <div class="mt-4 space-y-4">
                @foreach ($reviews as $review)
                    @php($reply = $review->replies->first())
                    <article class="rounded-xl border border-slate-200 bg-white p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-rating-stars :rating="$review->rating" />
                                @if ($review->isVerified())
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Verified purchase') }}</span>
                                @endif
                                <span class="text-xs text-slate-500">
                                    {{ $review->isShopReview() ? __('Shop review') : $review->listing->variant->product->name.' · '.$review->listing->variant->label }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">{{ $review->author?->display_name }} · {{ $review->created_at->timezone('Asia/Dhaka')->format('d M Y') }}</p>
                        </div>
                        @if ($review->title)
                            <h2 class="mt-2 font-semibold">{{ $review->title }}</h2>
                        @endif
                        <p class="mt-1 whitespace-pre-line text-slate-700">{{ $review->body }}</p>

                        @if ($reply)
                            <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm">
                                <p class="font-medium">{{ __('Your reply') }}</p>
                                <form method="POST" action="{{ route('workspace.reviews.replies.update', [$shop, $reply]) }}" class="mt-2 space-y-2">
                                    @csrf
                                    @method('PUT')
                                    <label for="reply-{{ $reply->getKey() }}" class="sr-only">{{ __('Your reply') }}</label>
                                    <textarea id="reply-{{ $reply->getKey() }}" name="body" rows="2" required maxlength="1000" class="w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ $reply->body }}</textarea>
                                    <div class="flex gap-3">
                                        <button type="submit" class="rounded-lg border border-brand-600 px-3 py-1.5 font-medium text-brand-700 hover:bg-brand-50">{{ __('Save reply') }}</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('workspace.reviews.replies.hide', [$shop, $reply]) }}" class="mt-2">
                                    @csrf
                                    <button type="submit" class="text-sm font-medium text-rose-700 hover:underline">{{ __('Remove reply') }}</button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('workspace.reviews.reply', [$shop, $review]) }}" class="mt-4 space-y-2">
                                @csrf
                                <label for="reply-{{ $review->getKey() }}" class="block text-sm font-medium">{{ __('Reply publicly') }}</label>
                                <textarea id="reply-{{ $review->getKey() }}" name="body" rows="2" required maxlength="1000" class="w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500"></textarea>
                                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">{{ __('Post reply') }}</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="mt-4">{{ $reviews->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
