@php
    $isNew = ! $promotion->exists;
    $zone = $shop->mall->timezone;
    $fmt = fn ($value) => $value?->timezone($zone)->format('Y-m-d\TH:i');
@endphp

<x-layouts.customer :title="($isNew ? __('New promotion') : $promotion->title).' · '.$shop->name">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('workspace.promotions.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Promotions') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ $isNew ? __('New promotion') : __('Edit :title', ['title' => $promotion->title]) }}</h1>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ $isNew ? route('workspace.promotions.store', $shop) : route('workspace.promotions.update', [$shop, $promotion]) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <x-form-field name="title" :label="__('Title')" :value="$promotion->title" required maxlength="120" />

            <div>
                <label for="description" class="block text-sm font-medium">{{ __('Description (shown to customers)') }}</label>
                <textarea id="description" name="description" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('description', $promotion->description) }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="discount_type" class="block text-sm font-medium">{{ __('Discount') }}</label>
                    <select id="discount_type" name="discount_type" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(old('discount_type', $promotion->discount_type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('discount_type') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <x-form-field name="discount_value" type="number" :label="__('Value (percent, or BDT)')" :value="$promotion->discount_value" required min="0.01" step="0.01" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="coupon_code" :label="__('Coupon code (empty = storewide)')" :value="$promotion->coupon_code" maxlength="30" :hint="__('Letters, digits, hyphens. Customers type it when ordering.')" />
                <x-form-field name="min_subtotal" type="number" :label="__('Minimum order (BDT)')" :value="$promotion->min_subtotal" min="0" step="0.01" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="starts_at" type="datetime-local" :label="__('Starts')" :value="$fmt($promotion->starts_at)" required :hint="__('Mall timezone (:zone)', ['zone' => $zone])" />
                <x-form-field name="ends_at" type="datetime-local" :label="__('Ends')" :value="$fmt($promotion->ends_at)" required />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="usage_limit" type="number" :label="__('Total uses (empty = unlimited)')" :value="$promotion->usage_limit" min="1" step="1" />
                <x-form-field name="per_customer_limit" type="number" :label="__('Uses per customer')" :value="$promotion->per_customer_limit" required min="1" max="10" step="1" />
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ $isNew ? __('Create promotion') : __('Save promotion') }}</button>
        </form>
    </div>
</x-layouts.customer>
