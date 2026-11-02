@props(['open', 'closedPermanently' => false])

@if ($closedPermanently)
    <span {{ $attributes->class('inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700') }}>
        {{ __('Closed') }}
    </span>
@elseif ($open)
    <span {{ $attributes->class('inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700') }}>
        <span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>{{ __('Open now') }}
    </span>
@else
    <span {{ $attributes->class('inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800') }}>
        <span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>{{ __('Closed now') }}
    </span>
@endif
