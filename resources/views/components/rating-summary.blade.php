@props(['summary'])

<div {{ $attributes->class(['flex flex-wrap items-center gap-6']) }}>
    <div class="text-center">
        <p class="text-4xl font-semibold tabular-nums">{{ $summary->averageRating === null ? '—' : number_format($summary->averageRating, 1) }}</p>
        <x-rating-stars :rating="$summary->averageRating" class="text-lg" />
        <p class="mt-1 text-sm text-slate-600">{{ trans_choice(':count review|:count reviews', $summary->reviewCount, ['count' => $summary->reviewCount]) }}</p>
        @if ($summary->verifiedCount > 0)
            <p class="text-xs text-emerald-700">{{ trans_choice(':count verified purchase|:count verified purchases', $summary->verifiedCount, ['count' => $summary->verifiedCount]) }}</p>
        @endif
    </div>

    @if ($summary->reviewCount > 0)
        <dl class="min-w-52 flex-1 space-y-1 text-sm" aria-label="{{ __('Rating breakdown') }}">
            @foreach ([5, 4, 3, 2, 1] as $star)
                <div class="flex items-center gap-2">
                    <dt class="w-12 text-slate-600">{{ __(':count stars', ['count' => $star]) }}</dt>
                    <dd class="flex flex-1 items-center gap-2">
                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><span class="block h-full rounded-full bg-amber-400" style="width: {{ $summary->percentOf($star) }}%"></span></span>
                        <span class="w-8 text-right tabular-nums text-slate-600">{{ $summary->distribution[$star] }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
