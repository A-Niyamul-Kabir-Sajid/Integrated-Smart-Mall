<x-layouts.customer :title="__('Add product').' · '.$shop->name">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        <a href="{{ route('workspace.catalogue.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Catalogue') }}</a>
        <h1 class="text-2xl font-semibold">{{ __('Add product') }}</h1>
        @error('business') <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('workspace.catalogue.store', $shop) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <input type="hidden" name="mode" value="existing">
            <h2 class="font-semibold">{{ __('Sell a product already in the mall catalogue') }}</h2>
            <div>
                <label for="variant_id" class="block text-sm font-medium">{{ __('Product') }}</label>
                <select id="variant_id" name="variant_id" required class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('Choose a product') }}</option>
                    @foreach ($variants as $variant)
                        <option value="{{ $variant->variant_id }}" @selected(old('variant_id') === $variant->variant_id)>{{ $variant->product->name }} · {{ $variant->label }}</option>
                    @endforeach
                </select>
                @error('variant_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            @include('shop-owner.catalogue._listing-fields', ['listing' => null])
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Add to my catalogue') }}</button>
        </form>

        <form method="POST" action="{{ route('workspace.catalogue.store', $shop) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <input type="hidden" name="mode" value="new">
            <h2 class="font-semibold">{{ __('Create a new product') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="name" :label="__('Product name')" required maxlength="160" />
                <x-form-field name="brand" :label="__('Brand')" maxlength="100" />
                <div>
                    <label for="product_category_id" class="block text-sm font-medium">{{ __('Category') }}</label>
                    <select id="product_category_id" name="product_category_id" required class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        <option value="">{{ __('Choose a category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->product_category_id }}" @selected(old('product_category_id') === $category->product_category_id)>{{ __($category->name) }}</option>
                        @endforeach
                    </select>
                    @error('product_category_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <x-form-field name="variant_label" :label="__('Option (size, volume, colour…)')" required maxlength="120" placeholder="500 ml bottle" />
            </div>
            <div>
                <label for="description" class="block text-sm font-medium">{{ __('Description') }}</label>
                <textarea id="description" name="description" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description') }}</textarea>
            </div>
            @include('shop-owner.catalogue._listing-fields', ['listing' => null])
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Create and add') }}</button>
        </form>
    </div>
</x-layouts.customer>
