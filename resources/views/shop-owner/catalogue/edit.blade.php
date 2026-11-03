<x-layouts.customer :title="$product->name.' · '.$shop->name">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        <a href="{{ route('workspace.catalogue.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Catalogue') }}</a>
        <div>
            <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
            <p class="text-slate-600">{{ $listing->variant->label }} · {{ __($product->category->name) }}
                @if ($listing->status === 'archived') · <span class="font-medium text-amber-700">{{ __('Archived') }}</span> @endif
            </p>
        </div>
        @error('business') <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('workspace.catalogue.update', [$shop, $listing]) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')
            <h2 class="font-semibold">{{ __('Your listing') }}</h2>
            @include('shop-owner.catalogue._listing-fields', ['listing' => $listing])
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Save listing') }}</button>
            </div>
        </form>

        <form method="POST" action="{{ route($listing->status === 'archived' ? 'workspace.catalogue.restore' : 'workspace.catalogue.archive', [$shop, $listing]) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                {{ $listing->status === 'archived' ? __('Restore listing') : __('Archive listing') }}
            </button>
        </form>

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="shared-heading">
            <h2 id="shared-heading" class="font-semibold">{{ __('Shared product details') }}</h2>
            @if ($canEditProduct)
                <form method="POST" action="{{ route('workspace.catalogue.product.update', [$shop, $listing]) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form-field name="name" :label="__('Product name')" :value="$product->name" required maxlength="160" />
                        <x-form-field name="brand" :label="__('Brand')" :value="$product->brand" maxlength="100" />
                        <div class="sm:col-span-2">
                            <label for="product_category_id" class="block text-sm font-medium">{{ __('Category') }}</label>
                            <select id="product_category_id" name="product_category_id" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->product_category_id }}" @selected($product->product_category_id === $category->product_category_id)>{{ __($category->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium">{{ __('Description') }}</label>
                        <textarea id="description" name="description" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description', $product->description) }}</textarea>
                    </div>
                    <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Save product details') }}</button>
                </form>

                <form method="POST" action="{{ route('workspace.catalogue.images.store', [$shop, $listing]) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                    @csrf
                    <div>
                        <label for="image" class="block text-sm font-medium">{{ __('Add a photo') }}</label>
                        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required class="mt-1 text-sm">
                        @error('image') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <x-form-field name="alt_text" :label="__('Describe the photo')" required maxlength="200" />
                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Upload') }}</button>
                </form>
            @else
                <p class="text-sm text-slate-600">{{ __('Other shops also sell this product, so only the mall office can change its shared details.') }}</p>
            @endif
            <p class="text-xs text-slate-500">{{ trans_choice(':count photo|:count photos', $product->images->count(), ['count' => $product->images->count()]) }}</p>
        </section>
    </div>
</x-layouts.customer>
