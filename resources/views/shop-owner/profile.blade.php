@php
    $days = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')];
@endphp

<x-layouts.customer :title="__('Shop profile').' · '.$shop->name">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Shop profile and opening hours') }}</h1>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('workspace.profile.update', $shop) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-semibold">{{ __('Profile') }}</h2>
            <x-form-field name="name" :label="__('Shop name')" :value="$shop->name" required maxlength="150" />
            <x-form-field name="contact_phone" :label="__('Contact number')" :value="$shop->contact_phone" maxlength="30" />
            <div>
                <label for="description" class="block text-sm font-medium">{{ __('Description') }}</label>
                <textarea id="description" name="description" rows="3" maxlength="1000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description', $shop->description) }}</textarea>
                @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 font-medium text-white hover:bg-brand-700">{{ __('Save profile') }}</button>
        </form>

        <form method="POST" action="{{ route('workspace.hours.update', $shop) }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-semibold">{{ __('Opening hours') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Times are in the mall timezone (:zone). Leave a day empty when the shop is closed.', ['zone' => $shop->mall->timezone]) }}</p>
            <div class="mt-4 space-y-3">
                @foreach ($days as $weekday => $label)
                    @php($interval = $hours->get($weekday)?->first())
                    <div class="grid items-end gap-3 sm:grid-cols-[8rem_1fr_1fr_auto]">
                        <p class="font-medium">{{ $label }}</p>
                        <div>
                            <label for="opens-{{ $weekday }}" class="block text-xs text-slate-600">{{ __('Opens') }}</label>
                            <input id="opens-{{ $weekday }}" type="time" name="hours[{{ $weekday }}][opens_at]" value="{{ old("hours.$weekday.opens_at", $interval ? substr($interval->opens_at, 0, 5) : '') }}" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="closes-{{ $weekday }}" class="block text-xs text-slate-600">{{ __('Closes') }}</label>
                            <input id="closes-{{ $weekday }}" type="time" name="hours[{{ $weekday }}][closes_at]" value="{{ old("hours.$weekday.closes_at", $interval ? substr($interval->closes_at, 0, 5) : '') }}" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-sm">
                            <input type="checkbox" name="hours[{{ $weekday }}][closes_next_day]" value="1" @checked(old("hours.$weekday.closes_next_day", $interval?->closes_next_day)) class="size-4 accent-brand-600">
                            {{ __('Next day') }}
                        </label>
                    </div>
                @endforeach
            </div>
            <button type="submit" class="mt-5 rounded-lg bg-brand-600 px-5 py-2 font-medium text-white hover:bg-brand-700">{{ __('Save opening hours') }}</button>
        </form>
    </div>
</x-layouts.customer>
