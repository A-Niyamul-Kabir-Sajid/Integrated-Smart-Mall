@php($completed = $purchase->status->value === 'completed')

<x-layouts.customer :title="__('Sale :ref', ['ref' => $purchase->external_receipt_ref]).' · '.$shop->name">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('workspace.sales.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Counter sales') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $purchase->external_receipt_ref }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ ucfirst($purchase->status->value) }}</span>
        </div>
        <p class="text-slate-600">{{ $purchase->purchased_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ $purchase->buyer?->display_name ?? __('Walk-in customer') }} · ৳{{ number_format((float) $purchase->total_amount, 2) }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if (session('review_code'))
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                <p class="font-medium">{{ __('Review code for line :line (shown only now)', ['line' => session('review_code.line')]) }}</p>
                <p class="mt-1 break-all font-mono text-base">{{ session('review_code.secret') }}</p>
            </div>
        @endif

        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-2">{{ __('Product') }}</th>
                        <th scope="col" class="px-4 py-2 text-right">{{ __('Qty') }}</th>
                        <th scope="col" class="px-4 py-2 text-right">{{ __('Price') }}</th>
                        <th scope="col" class="px-4 py-2 text-right">{{ __('Total') }}</th>
                        <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Review code') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($purchase->items as $item)
                        <tr>
                            <td class="px-4 py-2">{{ $item->product_label_snapshot }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $item->unit_price_amount, 2) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $item->line_total_amount, 2) }}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($completed && $purchase->buyer_user_id)
                                    @if ($item->verificationToken)
                                        <span class="text-xs text-slate-500">{{ __('Code issued') }}</span>
                                    @else
                                        <form method="POST" action="{{ route('workspace.sales.review-code', [$shop, $purchase, $item]) }}">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium hover:bg-slate-50" aria-label="{{ __('Issue a review code for :name', ['name' => $item->product_label_snapshot]) }}">{{ __('Issue review code') }}</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-4">
            <a href="{{ route('workspace.sales.receipt', [$shop, $purchase]) }}" class="font-medium text-brand-700 hover:underline">{{ __('Open the receipt') }}@if ($receipt) ({{ $receipt->receipt_number }})@endif</a>
        </div>

        @include('receipts._return-form', ['purchase' => $purchase, 'returnForm' => $returnForm])

        @if ($completed)
            <form method="POST" action="{{ route('workspace.sales.void', [$shop, $purchase]) }}" class="mt-8 flex flex-wrap items-end gap-2 border-t border-slate-200 pt-6">
                @csrf
                <div>
                    <label for="void-reason" class="block text-xs font-medium">{{ __('Entered by mistake? Reason for voiding') }}</label>
                    <input id="void-reason" name="reason" required minlength="3" maxlength="500" class="mt-1 w-72 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                </div>
                <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Void the sale') }}</button>
            </form>
        @endif
    </div>
</x-layouts.customer>
