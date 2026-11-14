<x-layouts.customer :title="__('Record a sale').' · '.$shop->name">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('workspace.sales.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Counter sales') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Record a sale') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Enter what was sold. Stock goes down and a receipt is issued. Leave the customer empty for a walk-in sale.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror
        @error('lines') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('workspace.sales.store', $shop) }}" class="mt-6 space-y-6">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="buyer_contact" :label="__('Customer email or mobile (optional)')" maxlength="254" :hint="__('Needed to issue a verified-review code later.')" />
                <x-form-field name="receipt_ref" :label="__('Receipt reference (optional)')" maxlength="120" :hint="__('Left empty, one is generated.')" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Product') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ __('List price') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Qty') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Price charged') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Discount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($listings as $listing)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">{{ $listing->variant->product->name }} <span class="text-slate-500">· {{ $listing->variant->label }}</span></th>
                                <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $listing->price_amount, 2) }}</td>
                                <td class="px-4 py-2"><input type="number" min="0" name="lines[{{ $listing->getKey() }}][quantity]" value="{{ old('lines.'.$listing->getKey().'.quantity', 0) }}" aria-label="{{ __('Quantity of :name', ['name' => $listing->variant->product->name]) }}" class="w-20 rounded-lg px-2 py-1.5 text-right ring-1 ring-slate-300"></td>
                                <td class="px-4 py-2"><input type="number" min="0" step="0.01" name="lines[{{ $listing->getKey() }}][unit_price_amount]" value="{{ old('lines.'.$listing->getKey().'.unit_price_amount') }}" placeholder="{{ __('list price') }}" aria-label="{{ __('Price charged for :name', ['name' => $listing->variant->product->name]) }}" class="w-28 rounded-lg px-2 py-1.5 text-right ring-1 ring-slate-300"></td>
                                <td class="px-4 py-2"><input type="number" min="0" step="0.01" name="lines[{{ $listing->getKey() }}][line_discount_amount]" value="{{ old('lines.'.$listing->getKey().'.line_discount_amount') }}" placeholder="0.00" aria-label="{{ __('Discount on :name', ['name' => $listing->variant->product->name]) }}" class="w-24 rounded-lg px-2 py-1.5 text-right ring-1 ring-slate-300"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Record the sale') }}</button>
        </form>
    </div>
</x-layouts.customer>
