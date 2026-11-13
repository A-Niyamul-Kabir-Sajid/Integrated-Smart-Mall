<x-layouts.customer :title="__('Receipt')">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ $backUrl }}" class="text-sm font-medium text-brand-700 hover:underline print:hidden">&larr; {{ __('Back') }}</a>

        <article class="mt-3 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="receipt-heading">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 id="receipt-heading" class="text-xl font-semibold">{{ $purchase->shop->name }}</h1>
                    <p class="text-sm text-slate-600">{{ $purchase->shop->mall->name }}</p>
                </div>
                <div class="text-right text-sm">
                    <p class="font-mono font-medium">{{ $receipt?->receipt_number ?? '—' }}</p>
                    <p class="text-slate-600">{{ $purchase->purchased_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</p>
                    <p class="text-slate-600">{{ __('Ref :ref', ['ref' => $purchase->external_receipt_ref]) }}</p>
                </div>
            </header>

            @if ($purchase->status->value !== 'completed')
                <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">{{ __('This sale is :status.', ['status' => $purchase->status->value]) }}</p>
            @endif

            <table class="mt-5 w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500">
                    <tr>
                        <th scope="col" class="py-2">{{ __('Product') }}</th>
                        <th scope="col" class="py-2 text-right">{{ __('Qty') }}</th>
                        <th scope="col" class="py-2 text-right">{{ __('Price') }}</th>
                        <th scope="col" class="py-2 text-right">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($purchase->items as $item)
                        <tr>
                            <td class="py-2">{{ $item->product_label_snapshot }}@if ((float) $item->line_discount_amount > 0) <span class="block text-xs text-slate-500">{{ __('Discount') }} −৳{{ number_format((float) $item->line_discount_amount, 2) }}</span>@endif</td>
                            <td class="py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="py-2 text-right tabular-nums">৳{{ number_format((float) $item->unit_price_amount, 2) }}</td>
                            <td class="py-2 text-right tabular-nums">৳{{ number_format((float) $item->line_total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-slate-300">
                    <tr><td colspan="3" class="py-2 text-right font-semibold">{{ __('Total') }}</td><td class="py-2 text-right font-semibold tabular-nums">৳{{ number_format((float) $purchase->total_amount, 2) }}</td></tr>
                </tfoot>
            </table>
            <p class="mt-4 text-xs text-slate-500">{{ __('Recorded by the shop. No payment was processed through this site.') }}</p>
        </article>

        <p class="mt-4 print:hidden"><button type="button" onclick="window.print()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Print') }}</button></p>
    </div>
</x-layouts.customer>
