{{-- The visitor's own saved spot and directions back (ISM-97, D-013). --}}
<div class="rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="my-parking-heading">
    <h3 id="my-parking-heading" class="font-semibold">{{ __('My parking spot') }}</h3>
    @guest
        <p class="mt-2 text-sm text-slate-600">{{ __('Sign in to remember where you parked.') }} <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline">{{ __('Sign in') }}</a></p>
    @else
        @if ($savedSpot)
            <p class="mt-2 text-sm">
                {{ __('You parked in :zone on :floor', ['zone' => $savedSpot->zone->code.' · '.$savedSpot->zone->name, 'floor' => __($savedSpot->zone->floor->name)]) }}@if ($savedSpot->bay_label), {{ __('bay :bay', ['bay' => $savedSpot->bay_label]) }}@endif.
                <span class="text-xs text-slate-500">{{ __('Saved :time', ['time' => $savedSpot->saved_at->timezone($mall->timezone)->format('d M, H:i')]) }}</span>
            </p>
            @if ($savedSpot->note)
                <p class="text-sm text-slate-600">{{ $savedSpot->note }}</p>
            @endif

            <form method="GET" action="{{ route('facilities.index', ['publicMall' => $mall->slug]) }}#parking" class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="from-floor" class="block text-xs font-medium text-slate-600">{{ __('I am on') }}</label>
                    <select id="from-floor" name="from" class="mt-1 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300">
                        @foreach ($floors as $floor)
                            <option value="{{ $floor->code }}" @selected($fromFloor?->is($floor))>{{ __($floor->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Take me back to my car') }}</button>
            </form>

            @if ($directions !== [])
                <ol class="mt-4 list-decimal space-y-1 rounded-lg bg-brand-50 py-3 pl-8 pr-4 text-sm text-brand-900" aria-label="{{ __('Directions back to your car') }}">
                    @foreach ($directions as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            @endif
        @else
            <p class="mt-2 text-sm text-slate-600">{{ __('No spot saved for this mall yet.') }}</p>
        @endif

        <form method="POST" action="{{ route('parking.store', ['publicMall' => $mall->slug]) }}" class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4">
            @csrf
            <div>
                <label for="parking_zone_id" class="block text-xs font-medium text-slate-600">{{ __('Zone') }}</label>
                <select id="parking_zone_id" name="parking_zone_id" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300">
                    @foreach ($parkingZones as $zone)
                        <option value="{{ $zone->parking_zone_id }}" @selected($savedSpot?->parking_zone_id === $zone->parking_zone_id)>{{ $zone->code }} · {{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-form-field name="bay_label" :label="__('Bay (optional)')" :value="$savedSpot?->bay_label" maxlength="20" :placeholder="__('e.g. 14')" />
            <x-form-field name="note" :label="__('Note (optional)')" :value="$savedSpot?->note" maxlength="200" :placeholder="__('e.g. next to the pillar')" />
            <button type="submit" class="self-end rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ $savedSpot ? __('Update my spot') : __('Save my spot') }}</button>
        </form>

        @if ($savedSpot)
            <form method="POST" action="{{ route('parking.destroy', ['publicMall' => $mall->slug]) }}" class="mt-3">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-medium text-rose-700 hover:underline">{{ __('Clear my spot') }}</button>
            </form>
        @endif
    @endguest
</div>
