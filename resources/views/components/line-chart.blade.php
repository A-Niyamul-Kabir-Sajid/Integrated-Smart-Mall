@props(['history', 'forecast', 'caption'])

@php
    $recent = array_slice($history, -42, null, true);
    $values = array_values($recent);
    $past = count($values);
    $total = $past + count($forecast);
    $peak = max(1.0, (float) max([...$values, ...array_column($forecast, 'high')]));
    $width = 720;
    $height = 220;
    $left = 8;
    $right = 8;
    $top = 10;
    $bottom = 14;
    $x = fn (int $i): float => $left + ($width - $left - $right) * $i / max(1, $total - 1);
    $y = fn (float $v): float => $top + ($height - $top - $bottom) * (1 - $v / $peak);
    $line = fn (array $points): string => implode(' ', array_map(fn ($p): string => round($p[0], 1).','.round($p[1], 1), $points));

    $historyPoints = [];
    foreach ($values as $i => $v) { $historyPoints[] = [$x($i), $y($v)]; }

    $pointPoints = $baselinePoints = $upper = $lower = [];
    foreach ($forecast as $k => $row) {
        $i = $past + $k;
        $pointPoints[] = [$x($i), $y($row['point'])];
        $baselinePoints[] = [$x($i), $y($row['baseline'])];
        $upper[] = [$x($i), $y($row['high'])];
        $lower[] = [$x($i), $y($row['low'])];
    }
    $band = $line([...$upper, ...array_reverse($lower)]);
@endphp

<figure class="mt-3 rounded-xl border border-slate-200 bg-white p-3">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full" role="img" aria-label="{{ $caption }}">
        <line x1="{{ $left }}" y1="{{ $y(0.0) }}" x2="{{ $width - $right }}" y2="{{ $y(0.0) }}" class="stroke-slate-300" stroke-width="1" />
        <line x1="{{ $x($past - 1) }}" y1="{{ $top }}" x2="{{ $x($past - 1) }}" y2="{{ $height - $bottom }}" class="stroke-slate-300" stroke-width="1" stroke-dasharray="3 3" />
        @if ($band !== '') <polygon points="{{ $band }}" class="fill-brand-500" fill-opacity="0.18" /> @endif
        <polyline points="{{ $line($historyPoints) }}" fill="none" class="stroke-slate-600" stroke-width="1.5" />
        <polyline points="{{ $line([end($historyPoints) ?: [0, 0], ...$baselinePoints]) }}" fill="none" class="stroke-slate-400" stroke-width="1.5" stroke-dasharray="4 3" />
        <polyline points="{{ $line([end($historyPoints) ?: [0, 0], ...$pointPoints]) }}" fill="none" class="stroke-brand-600" stroke-width="2" />
    </svg>
    <figcaption class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
        <span><span class="font-medium text-slate-700">&mdash;</span> {{ __('Recorded sales per day (last 42 days)') }}</span>
        <span><span class="font-medium text-brand-700">&mdash;</span> {{ __('Forecast with 80% interval (shaded)') }}</span>
        <span><span class="font-medium text-slate-500">- -</span> {{ __('Baseline: repeat last week') }}</span>
    </figcaption>
</figure>
