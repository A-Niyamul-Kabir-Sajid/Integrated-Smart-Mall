@props(['rating' => null])

@php($filled = (int) round((float) ($rating ?? 0)))

<span {{ $attributes->class(['inline-flex items-center gap-0.5 text-amber-500']) }} role="img" aria-label="{{ $rating === null ? __('No ratings yet') : __(':rating out of 5', ['rating' => number_format((float) $rating, 1)]) }}">
    @foreach (range(1, 5) as $star)
        <span aria-hidden="true" @class(['text-slate-300' => $star > $filled])>★</span>
    @endforeach
</span>
