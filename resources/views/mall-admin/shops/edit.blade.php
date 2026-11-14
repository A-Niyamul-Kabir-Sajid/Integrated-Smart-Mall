<x-layouts.customer :title="$shop->name.' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('mall-admin.shops.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Shops and tenants') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $shop->name }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ ucfirst($shop->status->value) }}</span>
        </div>
        <p class="text-sm text-slate-500">/malls/{{ $mall->slug }}/shops/{{ $shop->slug }}</p>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif
        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @php($isArchived = $shop->status->value === 'archived')

        <form method="POST" action="{{ route('mall-admin.shops.update', [$mall, $shop]) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')
            <h2 class="font-semibold">{{ __('Profile') }}</h2>
            <x-form-field name="name" :label="__('Shop name')" :value="$shop->name" required maxlength="150" :disabled="$isArchived" />
            <div>
                <label for="shop_category_id" class="block text-sm font-medium">{{ __('Category') }}</label>
                <select id="shop_category_id" name="shop_category_id" required @disabled($isArchived) class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    @foreach ($categories as $category)
                        <option value="{{ $category->shop_category_id }}" @selected(old('shop_category_id', $shop->shop_category_id) === $category->shop_category_id)>{{ __($category->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="description" class="block text-sm font-medium">{{ __('Description') }}</label>
                <textarea id="description" name="description" rows="3" maxlength="2000" @disabled($isArchived) class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description', $shop->description) }}</textarea>
            </div>
            <x-form-field name="contact_phone" :label="__('Contact number')" :value="$shop->contact_phone" maxlength="30" :disabled="$isArchived" />
            @unless ($isArchived)
                <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Save shop') }}</button>
            @endunless
        </form>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="units-heading">
            <h2 id="units-heading" class="font-semibold">{{ __('Units') }}</h2>
            @if ($shop->units->isEmpty())
                <p class="mt-2 text-sm text-slate-600">{{ __('The shop is not placed in a unit yet.') }}</p>
            @else
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @foreach ($shop->units as $unit)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span>{{ __($unit->floor->name) }} · <span class="font-medium">{{ $unit->unit_code }}</span>@if ($unit->area_sq_m) · {{ $unit->area_sq_m }} m²@endif</span>
                            @unless ($isArchived)
                                <form method="POST" action="{{ route('mall-admin.shops.units.release', [$mall, $shop, $unit]) }}">
                                    @csrf
                                    <button type="submit" class="font-medium text-rose-700 hover:underline">{{ __('Release unit') }}</button>
                                </form>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif
            @unless ($isArchived)
                <form method="POST" action="{{ route('mall-admin.shops.units.store', [$mall, $shop]) }}" class="mt-4 grid gap-3 sm:grid-cols-4 sm:items-end">
                    @csrf
                    <div>
                        <label for="unit-floor" class="block text-xs font-medium">{{ __('Floor') }}</label>
                        <select id="unit-floor" name="floor_id" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                            @foreach ($floors as $floor)
                                <option value="{{ $floor->floor_id }}">{{ __($floor->name) }} ({{ $floor->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="unit-code" class="block text-xs font-medium">{{ __('Unit code') }}</label>
                        <input id="unit-code" name="unit_code" required maxlength="40" class="mt-1 w-full rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <div>
                        <label for="unit-area" class="block text-xs font-medium">{{ __('Area (m²)') }}</label>
                        <input id="unit-area" name="area_sq_m" type="number" step="0.01" class="mt-1 w-full rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Assign unit') }}</button>
                </form>
            @endunless
        </section>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="owners-heading">
            <h2 id="owners-heading" class="font-semibold">{{ __('Owners') }}</h2>
            @if ($owners->isEmpty())
                <p class="mt-2 text-sm text-slate-600">{{ __('No owner yet. A shop needs one before it can be activated.') }}</p>
            @else
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @foreach ($owners as $owner)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span><span class="font-medium">{{ $owner->display_name }}</span> <span class="text-slate-500">· {{ $owner->email }}</span></span>
                            <form method="POST" action="{{ route('mall-admin.shops.owners.remove', [$mall, $shop, $owner]) }}">
                                @csrf
                                <button type="submit" class="font-medium text-rose-700 hover:underline" aria-label="{{ __('Remove :name as owner', ['name' => $owner->display_name]) }}">{{ __('Remove') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
            @unless ($isArchived)
                <form method="POST" action="{{ route('mall-admin.shops.owners.store', [$mall, $shop]) }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="owner-contact" class="block text-xs font-medium">{{ __('Email or mobile number') }}</label>
                        <input id="owner-contact" name="contact" required maxlength="255" class="mt-1 w-72 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Add owner') }}</button>
                </form>
            @endunless
        </section>

        @unless ($isArchived)
            <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="lifecycle-heading">
                <h2 id="lifecycle-heading" class="font-semibold">{{ __('Lifecycle') }}</h2>
                @if ($shop->status->value === 'draft')
                    <form method="POST" action="{{ route('mall-admin.shops.activate', [$mall, $shop]) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Activate the shop') }}</button>
                    </form>
                @elseif ($shop->status->value === 'closed')
                    <p class="mt-2 text-sm text-slate-600">{{ __('The shop is suspended. Reopen it from the shop applications page.') }}</p>
                @endif
                <form method="POST" action="{{ route('mall-admin.shops.archive', [$mall, $shop]) }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="archive-reason" class="block text-xs font-medium">{{ __('Reason for archiving') }}</label>
                        <input id="archive-reason" name="reason" required minlength="5" maxlength="1000" class="mt-1 w-80 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Archive the shop') }}</button>
                </form>
                <p class="mt-2 text-xs text-slate-500">{{ __('Archiving is final: the shop disappears for customers and its units are released. Open orders must be finished first.') }}</p>
            </section>
        @endunless
    </div>
</x-layouts.customer>
