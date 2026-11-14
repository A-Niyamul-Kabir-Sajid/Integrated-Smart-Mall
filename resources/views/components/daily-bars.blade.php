@props(['series', 'caption', 'heading' => null, 'decimals' => 0])

@php($peak = max(1, (float) $series->max()))

<div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <caption class="sr-only">{{ $caption }}</caption>
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr><th scope="col" class="px-4 py-2">{{ __('Day') }}</th><th scope="col" class="px-4 py-2">{{ $heading ?? __('Views') }}</th><th scope="col" class="w-1/2 px-4 py-2"><span class="sr-only">{{ __('Bar') }}</span></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($series as $day => $count)
                <tr>
                    <td class="whitespace-nowrap px-4 py-1.5">{{ \Illuminate\Support\Carbon::parse($day)->format('D d M') }}</td>
                    <td class="px-4 py-1.5 font-medium">{{ number_format($count, $decimals) }}</td>
                    <td class="px-4 py-1.5" aria-hidden="true"><div class="h-2 rounded bg-brand-500" style="width: {{ round($count / $peak * 100) }}%"></div></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
