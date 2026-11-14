<x-layouts.customer :title="__('Open a shop')">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('shop-applications.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your shop applications') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Open a shop') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Tell the mall about your shop. You become its owner now; the shop only opens to customers once the mall approves it.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('shop-applications.store') }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf

            <div>
                <label for="mall_id" class="block text-sm font-medium">{{ __('Mall') }}</label>
                <select id="mall_id" name="mall_id" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    @foreach ($malls as $mall)
                        <option value="{{ $mall->mall_id }}" @selected(old('mall_id') === $mall->mall_id)>{{ $mall->name }}</option>
                    @endforeach
                </select>
                @error('mall_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <x-form-field name="name" :label="__('Shop name')" required maxlength="150" />

            <div>
                <label for="shop_category_id" class="block text-sm font-medium">{{ __('Category') }}</label>
                <select id="shop_category_id" name="shop_category_id" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    @foreach ($categories as $category)
                        <option value="{{ $category->shop_category_id }}" @selected(old('shop_category_id') === $category->shop_category_id)>{{ __($category->name) }}</option>
                    @endforeach
                </select>
                @error('shop_category_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <x-form-field name="contact_phone" :label="__('Shop contact number (optional)')" maxlength="30" />

            <div>
                <label for="description" class="block text-sm font-medium">{{ __('What does the shop sell? (optional)') }}</label>
                <textarea id="description" name="description" rows="3" maxlength="1000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description') }}</textarea>
                @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="message" class="block text-sm font-medium">{{ __('A note for the mall office (optional)') }}</label>
                <textarea id="message" name="message" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('message') }}</textarea>
                @error('message') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 font-medium text-white hover:bg-brand-700">{{ __('Send application') }}</button>
        </form>
    </div>
</x-layouts.customer>
