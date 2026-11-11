@php($isNew = ! $facility->exists)

<x-layouts.customer :title="($isNew ? __('Add facility') : $facility->name).' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('facilities.admin.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Facilities') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ $isNew ? __('Add facility') : __('Edit :name', ['name' => $facility->name]) }}</h1>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ $isNew ? route('facilities.admin.store', $mall) : route('facilities.admin.update', [$mall, $facility]) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <x-form-field name="name" :label="__('Name')" :value="$facility->name" required maxlength="120" :placeholder="__('e.g. Washroom')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="facility_type" class="block text-sm font-medium">{{ __('Type') }}</label>
                    <select id="facility_type" name="facility_type" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(old('facility_type', $facility->facility_type?->value) === $type->value)>{{ __($type->label()) }}</option>
                        @endforeach
                    </select>
                    @error('facility_type') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="floor_id" class="block text-sm font-medium">{{ __('Floor') }}</label>
                    <select id="floor_id" name="floor_id" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        @foreach ($floors as $floor)
                            <option value="{{ $floor->floor_id }}" @selected(old('floor_id', $facility->floor_id) === $floor->floor_id)>{{ __($floor->name) }} ({{ $floor->code }})</option>
                        @endforeach
                    </select>
                    @error('floor_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-form-field name="location_note" :label="__('Where exactly (optional)')" :value="$facility->location_note" maxlength="160" :placeholder="__('e.g. Next to Fresh Mart')" />

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_accessible" value="1" @checked(old('is_accessible', $facility->is_accessible)) class="size-4 accent-brand-600">
                {{ __('Step-free access (wheelchair accessible)') }}
            </label>

            @unless ($isNew)
                <fieldset>
                    <legend class="text-sm font-medium">{{ __('Status') }}</legend>
                    <div class="mt-1 flex flex-wrap gap-4 text-sm">
                        @foreach ($statuses as $status)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="status" value="{{ $status->value }}" @checked(old('status', $facility->status?->value) === $status->value) class="accent-brand-600">
                                {{ ['open' => __('Open'), 'temporarily_closed' => __('Temporarily closed'), 'archived' => __('Archived')][$status->value] }}
                            </label>
                        @endforeach
                    </div>
                    @error('status') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </fieldset>
            @endunless

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ $isNew ? __('Add facility') : __('Save facility') }}</button>
        </form>
    </div>
</x-layouts.customer>
