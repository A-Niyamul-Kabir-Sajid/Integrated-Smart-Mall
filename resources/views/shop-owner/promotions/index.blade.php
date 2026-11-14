<x-layouts.customer :title="__('Promotions').' · '.$shop->name">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Promotions and coupons') }}</h1>
            <a href="{{ route('workspace.promotions.create', $shop) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('New promotion') }}</a>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ __('A promotion without a coupon code applies to every order while it runs and is shown on your shop page. A coupon code is shared by you and applies only when the customer enters it.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if ($promotions->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No promotions yet.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($promotions as $promotion)
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ $promotion->title }} <span class="ml-2 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $promotion->stateLabel() }}</span></p>
                                <p class="text-sm text-slate-600">
                                    {{ $promotion->discount_type->value === 'percent' ? rtrim(rtrim((string) $promotion->discount_value, '0'), '.').'%' : '৳'.number_format((float) $promotion->discount_value, 2) }}
                                    · {{ $promotion->coupon_code ? __('Code :code', ['code' => $promotion->coupon_code]) : __('Storewide') }}
                                    · {{ $promotion->starts_at->timezone($shop->mall->timezone)->format('d M Y H:i') }} → {{ $promotion->ends_at->timezone($shop->mall->timezone)->format('d M Y H:i') }}
                                    · {{ __('Used :count', ['count' => $promotion->times_redeemed]) }}@if ($promotion->usage_limit) / {{ $promotion->usage_limit }}@endif
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('workspace.promotions.edit', [$shop, $promotion]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50" aria-label="{{ __('Edit :title', ['title' => $promotion->title]) }}">{{ __('Edit') }}</a>
                                @if ($promotion->ends_at->isFuture())
                                    <form method="POST" action="{{ route($promotion->is_paused ? 'workspace.promotions.resume' : 'workspace.promotions.pause', [$shop, $promotion]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50" aria-label="{{ ($promotion->is_paused ? __('Resume :title', ['title' => $promotion->title]) : __('Pause :title', ['title' => $promotion->title])) }}">{{ $promotion->is_paused ? __('Resume') : __('Pause') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.promotions.end', [$shop, $promotion]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-rose-300 px-3 py-1.5 text-sm font-medium text-rose-800 hover:bg-rose-50" aria-label="{{ __('End :title now', ['title' => $promotion->title]) }}">{{ __('End now') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.customer>
