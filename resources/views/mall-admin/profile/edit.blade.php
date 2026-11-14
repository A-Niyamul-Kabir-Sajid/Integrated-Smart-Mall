<x-layouts.customer :title="__('Mall details').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Mall details') }}</h1>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif
        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('mall-admin.profile.update', $mall) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')
            <x-form-field name="name" :label="__('Name')" :value="$mall->name" required maxlength="150" />
            <x-form-field name="address" :label="__('Address')" :value="$mall->address" required maxlength="255" />
            <x-form-field name="city" :label="__('City')" :value="$mall->city" required maxlength="100" />
            <div>
                <label for="timezone" class="block text-sm font-medium">{{ __('Time zone') }}</label>
                <select id="timezone" name="timezone" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', $mall->timezone) === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
                @error('timezone') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <p class="text-sm text-slate-500">{{ __('Public address: /malls/:slug. The address never changes.', ['slug' => $mall->slug]) }}</p>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Save mall details') }}</button>
        </form>

        <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="status-heading">
            <h2 id="status-heading" class="font-semibold">{{ __('Visibility') }}: {{ ucfirst($mall->status->value) }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('A draft mall is hidden from customers. Archiving a mall is final.') }}</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach ($mall->status->allowedTransitions() as $next)
                    <form method="POST" action="{{ route('mall-admin.profile.status', $mall) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $next->value }}">
                        <button type="submit" @class(['rounded-lg px-4 py-2 text-sm font-medium', 'border border-rose-300 text-rose-800 hover:bg-rose-50' => $next->value === 'archived', 'bg-brand-600 text-white hover:bg-brand-700' => $next->value !== 'archived'])>{{ $next->value === 'archived' ? __('Archive the mall') : __('Make :status', ['status' => $next->value]) }}</button>
                    </form>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.customer>
