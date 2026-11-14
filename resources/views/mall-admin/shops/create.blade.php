<x-layouts.customer :title="__('Add a shop').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('mall-admin.shops.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Shops and tenants') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Add a shop') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('The shop starts as a draft. Name an owner, then activate it from its page.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('mall-admin.shops.store', $mall) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <x-form-field name="name" :label="__('Shop name')" required maxlength="150" />
            <x-form-field name="slug" :label="__('Public address')" required maxlength="80" :hint="__('Lower-case letters, digits and hyphens, e.g. cafe-brew. It never changes.')" />
            <div>
                <label for="shop_category_id" class="block text-sm font-medium">{{ __('Category') }}</label>
                <select id="shop_category_id" name="shop_category_id" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    @foreach ($categories as $category)
                        <option value="{{ $category->shop_category_id }}" @selected(old('shop_category_id') === $category->shop_category_id)>{{ __($category->name) }}</option>
                    @endforeach
                </select>
                @error('shop_category_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="description" class="block text-sm font-medium">{{ __('Description (optional)') }}</label>
                <textarea id="description" name="description" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description') }}</textarea>
            </div>
            <x-form-field name="contact_phone" :label="__('Contact number (optional)')" maxlength="30" />

            <fieldset class="rounded-lg border border-slate-200 p-4">
                <legend class="px-1 text-sm font-medium">{{ __('Place in the mall (optional)') }}</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="floor_id" class="block text-sm font-medium">{{ __('Floor') }}</label>
                        <select id="floor_id" name="floor_id" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300">
                            <option value="">{{ __('Not placed yet') }}</option>
                            @foreach ($floors as $floor)
                                <option value="{{ $floor->floor_id }}" @selected(old('floor_id') === $floor->floor_id)>{{ __($floor->name) }} ({{ $floor->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form-field name="unit_code" :label="__('Unit code')" maxlength="40" :placeholder="__('e.g. G-05')" />
                    <x-form-field name="area_sq_m" :label="__('Area (m²)')" type="number" step="0.01" />
                </div>
            </fieldset>

            <x-form-field name="owner_contact" :label="__('Owner email or mobile number (optional)')" maxlength="255" :hint="__('The owner must already have an account.')" />

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Create shop') }}</button>
        </form>
    </div>
</x-layouts.customer>
