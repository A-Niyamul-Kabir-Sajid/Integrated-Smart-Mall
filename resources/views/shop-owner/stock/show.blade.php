<x-layouts.customer :title="__('Stock').' · '.$listing->variant->product->name">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        <a href="{{ route('workspace.stock.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Stock') }}</a>
        <div>
            <h1 class="text-2xl font-semibold">{{ $listing->variant->product->name }}</h1>
            <p class="text-slate-600">{{ $listing->variant->label }} · <span class="font-mono text-xs">{{ $listing->seller_sku }}</span></p>
            <p class="mt-2 text-lg">
                @if ($stock)
                    {{ __('On hand: :count', ['count' => $stock->on_hand]) }}
                    @if ($stock->isLow()) <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">{{ __('Low stock') }}</span> @endif
                @else
                    <span class="text-slate-600">{{ __('Not tracked yet. Record a delivery or a count to start.') }}</span>
                @endif
            </p>
        </div>

        @error('business') <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <div class="grid gap-4 sm:grid-cols-2">
            <form method="POST" action="{{ route('workspace.stock.receive', [$shop, $listing]) }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
                @csrf
                <h2 class="font-semibold">{{ __('Record a delivery') }}</h2>
                <x-form-field name="quantity" type="number" :label="__('Quantity received')" required min="1" step="1" />
                <x-form-field name="reason" :label="__('Note (optional)')" maxlength="200" :placeholder="__('e.g. Supplier invoice 1042')" />
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Add to stock') }}</button>
            </form>

            <form method="POST" action="{{ route('workspace.stock.adjust', [$shop, $listing]) }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
                @csrf
                <h2 class="font-semibold">{{ __('Count the shelf') }}</h2>
                <x-form-field name="counted" type="number" :label="__('Quantity counted')" required min="0" step="1" />
                <x-form-field name="reason" :label="__('Reason')" required maxlength="200" :placeholder="__('e.g. Stocktake, damaged goods')" />
                <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Save the count') }}</button>
            </form>
        </div>

        @if ($stock)
            <form method="POST" action="{{ route('workspace.stock.threshold', [$shop, $listing]) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5">
                @csrf
                @method('PUT')
                <x-form-field name="threshold" type="number" :label="__('Alert me when stock is at or below')" :value="$stock->low_stock_threshold" required min="0" step="1" :hint="__('Zero switches the alert off.')" />
                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Save alert level') }}</button>
            </form>
        @endif

        <section aria-labelledby="ledger-heading">
            <h2 id="ledger-heading" class="font-semibold">{{ __('Stock history') }}</h2>
            @if ($movements->isEmpty())
                <p class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No movements yet.') }}</p>
            @else
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">{{ __('When') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('What') }}</th>
                                <th scope="col" class="px-4 py-2 text-right">{{ __('Change') }}</th>
                                <th scope="col" class="px-4 py-2 text-right">{{ __('Balance') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Who and why') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($movements as $movement)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap">{{ $movement->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-2">{{ $movement->movement_type->label() }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $movement->balance_after }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $movement->actor?->display_name ?? '—' }}@if ($movement->reason) · {{ $movement->reason }}@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $movements->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.customer>
