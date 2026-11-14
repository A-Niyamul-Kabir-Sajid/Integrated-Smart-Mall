<x-layouts.customer :title="__('New work order')" :mall="$mall">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('mall-admin.work-orders.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Work orders') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('New work order') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('For a job in a common area. For a shop\'s request, open the request and create the work order there.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('mall-admin.work-orders.store', $mall) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <x-form-field name="title" :label="__('Title')" required maxlength="150" />
            <div>
                <label for="description" class="block text-sm font-medium">{{ __('What needs doing?') }}</label>
                <textarea id="description" name="description" rows="4" required minlength="5" maxlength="2000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description') }}</textarea>
                @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="floor_id" class="block text-sm font-medium">{{ __('Floor (optional)') }}</label>
                    <select id="floor_id" name="floor_id" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300">
                        <option value="">{{ __('Not specified') }}</option>
                        @foreach ($floors as $floor)
                            <option value="{{ $floor->floor_id }}" @selected(old('floor_id') === $floor->floor_id)>{{ __($floor->name) }} ({{ $floor->code }})</option>
                        @endforeach
                    </select>
                </div>
                <x-form-field name="location_note" :label="__('Where exactly (optional)')" maxlength="160" />
                <div>
                    <label for="priority" class="block text-sm font-medium">{{ __('Priority') }}</label>
                    <select id="priority" name="priority" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-form-field name="due_on" type="date" :label="__('Due date (optional)')" />
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Create work order') }}</button>
        </form>
    </div>
</x-layouts.customer>
