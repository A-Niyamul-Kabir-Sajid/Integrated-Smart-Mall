<x-layouts.customer :title="__('Floors').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Floors') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('A floor with shop units or facilities cannot be archived. A floor code never changes.') }}</p>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif
        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <ul class="mt-6 space-y-3">
            @foreach ($floors as $floor)
                <li @class(['rounded-xl border border-slate-200 bg-white p-4', 'opacity-60' => $floor->status === 'archived'])>
                    <form method="POST" action="{{ route('mall-admin.floors.update', [$mall, $floor]) }}" class="grid gap-3 sm:grid-cols-5 sm:items-end">
                        @csrf
                        @method('PUT')
                        <div class="sm:col-span-2">
                            <label for="name-{{ $floor->floor_id }}" class="block text-xs font-medium">{{ $floor->code }} · {{ __('Name') }}</label>
                            <input id="name-{{ $floor->floor_id }}" name="name" value="{{ $floor->name }}" required maxlength="80" class="mt-1 w-full rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                        </div>
                        <div>
                            <label for="sort-{{ $floor->floor_id }}" class="block text-xs font-medium">{{ __('Order') }}</label>
                            <input id="sort-{{ $floor->floor_id }}" name="sort_order" type="number" value="{{ $floor->sort_order }}" required class="mt-1 w-full rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                        </div>
                        <div>
                            <label for="elev-{{ $floor->floor_id }}" class="block text-xs font-medium">{{ __('Elevation (m)') }}</label>
                            <input id="elev-{{ $floor->floor_id }}" name="elevation_m" type="number" step="0.001" value="{{ $floor->elevation_m }}" required class="mt-1 w-full rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                        </div>
                        <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Save') }}</button>
                    </form>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                        <span>{{ __(':units shop units · :facilities facilities', ['units' => $floor->units_count, 'facilities' => $floor->facilities_count]) }}@if ($floor->status === 'archived') · {{ __('Archived') }}@endif</span>
                        <form method="POST" action="{{ route($floor->status === 'archived' ? 'mall-admin.floors.restore' : 'mall-admin.floors.archive', [$mall, $floor]) }}">
                            @csrf
                            <button type="submit" class="font-medium text-brand-700 hover:underline">{{ $floor->status === 'archived' ? __('Restore floor') : __('Archive floor') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        <form method="POST" action="{{ route('mall-admin.floors.store', $mall) }}" class="mt-8 grid gap-3 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-5 sm:items-end">
            @csrf
            <h2 class="font-semibold sm:col-span-5">{{ __('Add a floor') }}</h2>
            <x-form-field name="code" :label="__('Code')" required maxlength="20" :placeholder="__('e.g. F2')" />
            <div class="sm:col-span-2"><x-form-field name="name" :label="__('Name')" required maxlength="80" /></div>
            <x-form-field name="sort_order" :label="__('Order')" type="number" required :value="($floors->max('sort_order') ?? 0) + 1" />
            <x-form-field name="elevation_m" :label="__('Elevation (m)')" type="number" step="0.001" required value="0" />
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700 sm:col-span-5 sm:w-fit">{{ __('Add floor') }}</button>
        </form>
    </div>
</x-layouts.customer>
