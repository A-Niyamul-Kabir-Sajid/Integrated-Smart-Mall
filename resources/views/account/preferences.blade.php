<x-layouts.customer :title="__('Preferences')">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-semibold">{{ __('Preferences') }}</h1>

        <form method="POST" action="{{ route('account.preferences.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <fieldset class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
                <legend class="px-1 font-semibold">{{ __('Language and mall') }}</legend>
                <div>
                    <label for="locale" class="block text-sm font-medium">{{ __('Language') }}</label>
                    <select id="locale" name="locale" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        <option value="en" @selected(old('locale', $preference->locale) === 'en')>English</option>
                        <option value="bn" lang="bn" @selected(old('locale', $preference->locale) === 'bn')>বাংলা</option>
                    </select>
                </div>
                <div>
                    <label for="default_mall_id" class="block text-sm font-medium">{{ __('Usual mall') }}</label>
                    <select id="default_mall_id" name="default_mall_id" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        <option value="">{{ __('No preference') }}</option>
                        @foreach ($malls as $mall)
                            <option value="{{ $mall->mall_id }}" @selected(old('default_mall_id', $preference->default_mall_id) === $mall->mall_id)>{{ $mall->name }}</option>
                        @endforeach
                    </select>
                    @error('default_mall_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <fieldset class="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
                <legend class="px-1 font-semibold">{{ __('Navigation') }}</legend>
                @foreach (['shortest_distance' => __('Shortest distance'), 'fastest' => __('Fastest'), 'accessible_distance' => __('Step-free (avoid stairs)')] as $mode => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="route_mode" value="{{ $mode }}" @checked(old('route_mode', $preference->route_mode->value) === $mode) class="size-4 accent-brand-600">
                        {{ $label }}
                    </label>
                @endforeach
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="ar_preferred" value="1" @checked(old('ar_preferred', $preference->ar_preferred)) class="size-4 accent-brand-600">
                    {{ __('Open camera (AR) guidance when my phone supports it') }}
                </label>
            </fieldset>

            <fieldset class="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
                <legend class="px-1 font-semibold">{{ __('Notifications and privacy') }}</legend>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="notify_by_email" value="1" @checked(old('notify_by_email', $preference->notify_by_email)) class="size-4 accent-brand-600">
                    {{ __('Email me updates') }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="notify_by_sms" value="1" @checked(old('notify_by_sms', $preference->notify_by_sms)) class="size-4 accent-brand-600">
                    {{ __('Text me updates') }}
                </label>
                <p class="text-xs text-slate-500">{{ __('Account security messages are always emailed. Updates also appear in your notification list.') }}</p>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="personalization_opt_in" value="1" @checked(old('personalization_opt_in', $preference->personalization_opt_in)) class="size-4 accent-brand-600">
                    {{ __('Use my activity to personalise suggestions') }}
                </label>
            </fieldset>

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Save preferences') }}</button>
        </form>
    </div>
</x-layouts.customer>
