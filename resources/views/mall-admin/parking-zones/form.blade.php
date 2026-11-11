@php($isNew = ! $zone->exists)

<x-layouts.customer :title="($isNew ? __('Add parking zone') : $zone->name).' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('facilities.admin.index', $mall) }}#zones" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Facilities') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ $isNew ? __('Add parking zone') : __('Edit :name', ['name' => $zone->name]) }}</h1>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ $isNew ? route('parking-zones.admin.store', $mall) : route('parking-zones.admin.update', [$mall, $zone]) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="code" :label="__('Code')" :value="$zone->code" required maxlength="20" :placeholder="__('e.g. B1-A')" />
                <x-form-field name="capacity" type="number" :label="__('Spaces')" :value="$zone->capacity" required min="1" max="10000" />
            </div>
            <x-form-field name="name" :label="__('Name')" :value="$zone->name" required maxlength="80" :placeholder="__('e.g. Zone A (near Lift A)')" />
            <div>
                <label for="floor_id" class="block text-sm font-medium">{{ __('Floor') }}</label>
                <select id="floor_id" name="floor_id" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    @foreach ($floors as $floor)
                        <option value="{{ $floor->floor_id }}" @selected(old('floor_id', $zone->floor_id) === $floor->floor_id)>{{ __($floor->name) }} ({{ $floor->code }})</option>
                    @endforeach
                </select>
            </div>

            @unless ($isNew)
                <fieldset>
                    <legend class="text-sm font-medium">{{ __('Status') }}</legend>
                    <div class="mt-1 flex gap-4 text-sm">
                        @foreach (['active' => __('Active'), 'archived' => __('Archived')] as $value => $label)
                            <label class="flex items-center gap-2"><input type="radio" name="status" value="{{ $value }}" @checked(old('status', $zone->status) === $value) class="accent-brand-600">{{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>
            @endunless

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ $isNew ? __('Add parking zone') : __('Save parking zone') }}</button>
        </form>
    </div>
</x-layouts.customer>
