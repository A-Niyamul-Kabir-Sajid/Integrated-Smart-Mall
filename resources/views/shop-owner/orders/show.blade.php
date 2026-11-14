@php($status = $order->status->value)

<x-layouts.customer :title="__('Order :number', ['number' => $order->order_number]).' · '.$shop->name">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('workspace.orders.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Orders') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $order->order_number }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $order->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $order->customer->display_name }} · {{ $order->kind->label() }} · {{ __('Collect by :time', ['time' => $order->pickup_by->timezone('Asia/Dhaka')->format('d M Y H:i')]) }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror
        @error('pickup_code') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-2">{{ __('Product') }}</th>
                        <th scope="col" class="px-4 py-2 text-right">{{ __('Qty') }}</th>
                        <th scope="col" class="px-4 py-2 text-right">{{ __('Price') }}</th>
                        <th scope="col" class="px-4 py-2 text-right">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-4 py-2">{{ $item->label_snapshot }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $item->unit_price_amount, 2) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $item->line_total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-slate-200">
                    @if ((float) $order->discount_amount > 0)
                        <tr><td colspan="3" class="px-4 py-2 text-right text-slate-600">{{ __('Discount') }}@if ($order->promotion_title) ({{ $order->promotion_title }})@endif</td><td class="px-4 py-2 text-right tabular-nums">−৳{{ number_format((float) $order->discount_amount, 2) }}</td></tr>
                    @endif
                    <tr><td colspan="3" class="px-4 py-2 text-right font-semibold">{{ __('To collect at the counter') }}</td><td class="px-4 py-2 text-right font-semibold tabular-nums">৳{{ number_format((float) $order->total_amount, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>

        @if ($order->customer_note)
            <p class="mt-4 text-sm"><span class="font-medium">{{ __('Customer note') }}:</span> {{ $order->customer_note }}</p>
        @endif

        @if ($order->status->isOpen())
            <section class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="actions-heading">
                <h2 id="actions-heading" class="font-semibold">{{ __('Actions') }}</h2>
                <div class="flex flex-wrap items-end gap-3">
                    @if ($status === 'requested')
                        <form method="POST" action="{{ route('workspace.orders.accept', [$shop, $order]) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Accept') }}</button>
                        </form>
                    @endif
                    @if ($status === 'accepted')
                        <form method="POST" action="{{ route('workspace.orders.ready', [$shop, $order]) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Mark ready for pickup') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('workspace.orders.reject', [$shop, $order]) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <div>
                            <label for="reject-reason" class="block text-xs font-medium">{{ __('Reason for declining') }}</label>
                            <input id="reject-reason" name="reason" required minlength="3" maxlength="500" class="mt-1 w-64 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Decline') }}</button>
                    </form>
                </div>

                @if (in_array($status, ['accepted', 'ready'], true))
                    <form method="POST" action="{{ route('workspace.orders.complete', [$shop, $order]) }}" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                        @csrf
                        <div>
                            <label for="pickup_code" class="block text-sm font-medium">{{ __('Customer pickup code') }}</label>
                            <input id="pickup_code" name="pickup_code" required maxlength="12" autocomplete="off" class="mt-1 w-40 rounded-lg px-3 py-2 font-mono uppercase ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Hand over and record the sale') }}</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($order->purchase)
            <p class="mt-4"><a href="{{ route('workspace.sales.show', [$shop, $order->purchase]) }}" class="font-medium text-brand-700 hover:underline">{{ __('Open the recorded sale and receipt') }}</a></p>
        @endif

        <section class="mt-8" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-semibold">{{ __('History') }}</h2>
            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($order->events as $event)
                    <li>{{ $event->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ \App\Modules\ShopOwnerOperations\Enums\OrderStatus::from($event->to_status)->label() }} · {{ $event->actor?->display_name ?? __('System') }}@if ($event->note) · {{ $event->note }}@endif</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.customer>
