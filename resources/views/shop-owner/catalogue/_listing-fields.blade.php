{{-- Expects $listing (a ShopProduct or null). --}}
<div class="grid gap-4 sm:grid-cols-3">
    <x-form-field name="seller_sku" :label="__('Your SKU')" :value="$listing?->seller_sku" required maxlength="80" />
    <x-form-field name="price_amount" type="number" :label="__('Price (BDT)')" :value="$listing?->price_amount" required min="0" step="0.01" />
    <div>
        <label for="availability_status" class="block text-sm font-medium">{{ __('Availability') }}</label>
        <select id="availability_status" name="availability_status" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
            @foreach (['available' => __('Available'), 'unavailable' => __('Out of stock'), 'unknown' => __('Availability unknown')] as $value => $label)
                <option value="{{ $value }}" @selected(old('availability_status', $listing?->availability_status ?? 'available') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('availability_status') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
    </div>
</div>
