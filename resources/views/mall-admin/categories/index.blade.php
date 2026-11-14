<x-layouts.customer :title="__('Shop categories')">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <p class="text-sm text-slate-500">{{ __('Platform administration') }}</p>
        <h1 class="text-2xl font-semibold">{{ __('Shop categories') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Categories are shared by every mall. Slugs never change.') }}</p>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif
        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <ul class="mt-6 space-y-3">
            @foreach ($categories as $category)
                <li class="rounded-xl border border-slate-200 bg-white p-4">
                    <form method="POST" action="{{ route('mall-admin.categories.update', $category) }}" class="grid gap-3 sm:grid-cols-5 sm:items-end">
                        @csrf
                        @method('PUT')
                        <div class="sm:col-span-2">
                            <label for="cat-name-{{ $category->shop_category_id }}" class="block text-xs font-medium">{{ $category->slug }} · {{ __('Name') }}</label>
                            <input id="cat-name-{{ $category->shop_category_id }}" name="name" value="{{ $category->name }}" required maxlength="100" class="mt-1 w-full rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="cat-parent-{{ $category->shop_category_id }}" class="block text-xs font-medium">{{ __('Inside') }}</label>
                            <select id="cat-parent-{{ $category->shop_category_id }}" name="parent_category_id" class="mt-1 w-full rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                                <option value="">{{ __('Top level') }}</option>
                                @foreach ($categories->where('shop_category_id', '!=', $category->shop_category_id) as $parent)
                                    <option value="{{ $parent->shop_category_id }}" @selected($category->parent_category_id === $parent->shop_category_id)>{{ $parent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Save') }}</button>
                    </form>
                    <p class="mt-1 text-xs text-slate-500">{{ __(':count shops', ['count' => $category->shops_count]) }}</p>
                </li>
            @endforeach
        </ul>

        <form method="POST" action="{{ route('mall-admin.categories.store') }}" class="mt-8 grid gap-3 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
            @csrf
            <h2 class="font-semibold sm:col-span-2">{{ __('Add a category') }}</h2>
            <x-form-field name="name" :label="__('Name')" required maxlength="100" />
            <x-form-field name="slug" :label="__('Slug')" required maxlength="80" :hint="__('Lower-case letters, digits and hyphens.')" />
            <div class="sm:col-span-2">
                <label for="parent_category_id" class="block text-sm font-medium">{{ __('Inside (optional)') }}</label>
                <select id="parent_category_id" name="parent_category_id" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300">
                    <option value="">{{ __('Top level') }}</option>
                    @foreach ($categories as $parent)
                        <option value="{{ $parent->shop_category_id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700 sm:col-span-2 sm:w-fit">{{ __('Add category') }}</button>
        </form>
    </div>
</x-layouts.customer>
