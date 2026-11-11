<x-layouts.customer :title="__('Facilities')" :mall="$mall">
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-8">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('Facilities') }}</h1>
            <p class="mt-1 text-slate-600">{{ __('Washrooms, prayer rooms, information desks and exits.') }}</p>
        </div>

        <form method="GET" action="{{ route('facilities.index', ['publicMall' => $mall->slug]) }}" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5" role="search" aria-label="{{ __('Filter facilities') }}">
            <div>
                <label for="facility-type" class="text-xs font-medium text-slate-600">{{ __('Type') }}</label>
                <select id="facility-type" name="type" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('All types') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ __($type->label()) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="facility-floor" class="text-xs font-medium text-slate-600">{{ __('Floor') }}</label>
                <select id="facility-floor" name="floor" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('All floors') }}</option>
                    @foreach ($floors as $floor)
                        <option value="{{ $floor->code }}" @selected(($filters['floor'] ?? null) === $floor->code)>{{ __($floor->name) }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" name="accessible_only" value="1" @checked(request()->boolean('accessible_only')) class="size-4 accent-brand-600">{{ __('Step-free only') }}</label>
            <label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" name="open_only" value="1" @checked(request()->boolean('open_only')) class="size-4 accent-brand-600">{{ __('Open only') }}</label>
            <button type="submit" class="self-end rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">{{ __('Filter') }}</button>
        </form>

        <section aria-labelledby="facility-list-heading">
            <h2 id="facility-list-heading" class="sr-only">{{ __('Facilities by floor') }}</h2>
            @if ($facilities->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600" aria-live="polite">{{ __('No facility matches these filters.') }}</p>
            @else
                <div class="grid gap-6 lg:grid-cols-3">
                    @foreach ($floors as $floor)
                        @continue(! $facilities->has($floor->floor_id))
                        <section class="rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="floor-{{ $floor->code }}-heading">
                            <h3 id="floor-{{ $floor->code }}-heading" class="flex items-center gap-2 font-semibold">
                                <span class="grid size-7 place-items-center rounded-md bg-brand-50 text-xs font-bold text-brand-700" aria-hidden="true">{{ $floor->code }}</span>{{ __($floor->name) }}
                            </h3>
                            <ul class="mt-3 space-y-3">
                                @foreach ($facilities[$floor->floor_id] as $facility)
                                    <li class="text-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium">{{ $facility->name }}</span>
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ __($facility->facility_type->label()) }}</span>
                                            @if ($facility->is_accessible)
                                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs text-sky-700">{{ __('Step-free') }}</span>
                                            @endif
                                            @if ($facility->status->value === 'temporarily_closed')
                                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Temporarily closed') }}</span>
                                            @endif
                                        </div>
                                        @if ($facility->location_note)
                                            <p class="text-xs text-slate-500">{{ $facility->location_note }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            @endif
            <p class="mt-3 text-xs text-slate-500">{{ __('Facilities will appear on the indoor map once it is published.') }}</p>
        </section>

        <section id="parking" class="space-y-4" aria-labelledby="parking-heading">
            <h2 id="parking-heading" class="text-xl font-semibold">{{ __('Parking') }}</h2>

            @error('business') <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

            @if ($parkingZones->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">{{ __('This mall has no parking zones.') }}</p>
            @else
                @include('customer.facilities._occupancy')

                @include('customer.facilities._my-parking')
            @endif
        </section>
    </div>
</x-layouts.customer>
