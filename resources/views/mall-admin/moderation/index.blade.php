@php
    $isReportedView = $view === 'reported';
@endphp

<x-layouts.customer :title="__('Review moderation').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <p class="text-sm text-slate-500">{{ __('Mall administration') }}</p>
        <h1 class="text-2xl font-semibold">{{ $mall->name }} · {{ __('Review moderation') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Reporters are never shown. Every decision needs a reason and is kept in the review history.') }}</p>

        @if ($errors->has('business'))
            <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $errors->first('business') }}</p>
        @endif
        @error('reason')
            <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p>
        @enderror

        <nav class="mt-6 flex gap-2 text-sm" aria-label="{{ __('Moderation queues') }}">
            <x-nav-link :href="route('moderation.index', $mall)" :active="$isReportedView">{{ __('Reported reviews') }}</x-nav-link>
            <x-nav-link :href="route('moderation.index', [$mall, 'view' => 'hidden'])" :active="! $isReportedView">{{ __('Hidden reviews') }}</x-nav-link>
        </nav>

        @if ($reviews->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ $isReportedView ? __('No open reports. Nothing to review right now.') : __('No hidden reviews.') }}</p>
        @else
            <div class="mt-4 space-y-5">
                @foreach ($reviews as $review)
                    <article class="rounded-xl border border-slate-200 bg-white p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-rating-stars :rating="$review->rating" />
                                @if ($review->isVerified())
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Verified purchase') }}</span>
                                @endif
                                <span class="text-xs text-slate-500">{{ $review->shop->name }} · {{ $review->isShopReview() ? __('Shop review') : $review->listing->variant->product->name.' · '.$review->listing->variant->label }}</span>
                            </div>
                            <p class="text-xs text-slate-500">{{ $review->author?->display_name }} · {{ $review->created_at->timezone('Asia/Dhaka')->format('d M Y') }}</p>
                        </div>
                        @if ($review->title)
                            <h2 class="mt-2 font-semibold">{{ $review->title }}</h2>
                        @endif
                        <p class="mt-1 whitespace-pre-line text-slate-700">{{ $review->body }}</p>

                        @if ($isReportedView)
                            <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm">
                                <p class="font-medium text-amber-900">{{ trans_choice(':count open report|:count open reports', $review->open_reports_count, ['count' => $review->open_reports_count]) }}</p>
                                <ul class="mt-1 space-y-1 text-amber-900">
                                    @foreach ($review->reports as $report)
                                        <li>
                                            {{ $report->reason_code->label() }}@if ($report->details): <span class="whitespace-pre-line">{{ $report->details }}</span>@endif
                                            <span class="text-xs text-amber-700">· {{ $report->created_at->timezone('Asia/Dhaka')->format('d M Y') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" class="mt-4 space-y-2">
                            @csrf
                            <label for="reason-{{ $review->getKey() }}" class="block text-sm font-medium">{{ __('Reason for your decision') }}</label>
                            <textarea id="reason-{{ $review->getKey() }}" name="reason" rows="2" required maxlength="1000" class="w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500"></textarea>
                            <div class="flex flex-wrap gap-2">
                                @if ($isReportedView)
                                    <button type="submit" formaction="{{ route('moderation.hide', [$mall, $review]) }}" class="rounded-lg bg-rose-600 px-4 py-2 font-medium text-white hover:bg-rose-700">{{ __('Hide review') }}</button>
                                    <button type="submit" formaction="{{ route('moderation.dismiss', [$mall, $review]) }}" class="rounded-lg border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50">{{ __('Dismiss reports') }}</button>
                                @else
                                    <button type="submit" formaction="{{ route('moderation.restore', [$mall, $review]) }}" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">{{ __('Restore review') }}</button>
                                @endif
                                @if ($review->isVerified())
                                    <button type="submit" formaction="{{ route('moderation.revoke-verification', [$mall, $review]) }}" class="rounded-lg border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50">{{ __('Remove verified badge') }}</button>
                                @endif
                            </div>
                        </form>

                        @if ($review->moderationActions->isNotEmpty())
                            <details class="mt-4 text-sm">
                                <summary class="cursor-pointer text-slate-600 hover:text-slate-900">{{ __('Moderation history') }} ({{ $review->moderationActions->count() }})</summary>
                                <ol class="mt-2 space-y-2 border-l-2 border-slate-200 pl-4">
                                    @foreach ($review->moderationActions as $action)
                                        <li>
                                            <span class="font-medium">{{ str_replace('_', ' ', ucfirst($action->action->value)) }}</span>
                                            <span class="text-slate-500">· {{ $action->previous_status->value }} → {{ $action->resulting_status->value }} · {{ $action->actor?->display_name }} · {{ $action->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</span>
                                            <p class="whitespace-pre-line text-slate-700">{{ $action->reason }}</p>
                                        </li>
                                    @endforeach
                                </ol>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="mt-4">{{ $reviews->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
