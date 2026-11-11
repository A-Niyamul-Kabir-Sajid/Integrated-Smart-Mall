{{-- Simulated occupancy per parking zone (ISM-100, D-013): always labelled, never presented as live data. --}}
<div class="rounded-xl border border-amber-200 bg-white p-5" aria-labelledby="occupancy-heading">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 id="occupancy-heading" class="font-semibold">{{ __('Free spaces right now') }}</h3>
        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">{{ __('Simulated data, not live') }}</span>
    </div>
    <p class="mt-1 text-xs text-slate-500">{{ __('There are no parking sensors yet. These numbers follow a typical day and only demonstrate the feature.') }}</p>
    <ul class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach ($parkingZones as $zone)
            @php($numbers = $occupancy[$zone->parking_zone_id])
            <li class="rounded-lg border border-slate-200 p-3 text-sm">
                <div class="flex justify-between gap-2">
                    <span class="font-medium">{{ $zone->code }} · {{ $zone->name }}</span>
                    <span class="text-slate-500">{{ __($zone->floor->name) }}</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-100" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $numbers['percent'] }}" aria-label="{{ __(':percent% full (simulated)', ['percent' => $numbers['percent']]) }}">
                    <div @class(['h-2 rounded-full', 'bg-emerald-500' => $numbers['percent'] < 70, 'bg-amber-500' => $numbers['percent'] >= 70 && $numbers['percent'] < 90, 'bg-rose-500' => $numbers['percent'] >= 90]) style="width: {{ $numbers['percent'] }}%"></div>
                </div>
                <p class="mt-1 text-xs text-slate-600">{{ __(':free of :capacity spaces free', ['free' => $numbers['free'], 'capacity' => $numbers['capacity']]) }}</p>
            </li>
        @endforeach
    </ul>
</div>

