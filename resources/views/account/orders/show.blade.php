@php($open = $order->status->isOpen())

<x-layouts.customer :title="__('Order :number', ['number' => $order->order_number])">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('orders.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your orders') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $order->order_number }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $order->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $order->shop->name }} · {{ $order->kind->label() }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if ($open)
            <section class="mt-6 rounded-xl border border-brand-200 bg-brand-50 p-5" aria-labelledby="pickup-heading">
                <h2 id="pickup-heading" class="font-semibold">{{ __('Your pickup code') }}</h2>
                <p class="mt-1 font-mono text-3xl tracking-widest">{{ $order->pickup_code }}</p>
                <p class="mt-1 text-sm text-slate-700">{{ __('Show this code at :shop to collect your order by :time.', ['shop' => $order->shop->name, 'time' => $order->pickup_by->timezone('Asia/Dhaka')->format('d M Y H:i')]) }}</p>
            </section>
        @endif

        <section class="mt-6" aria-labelledby="lines-heading">
            <h2 id="lines-heading" class="font-semibold">{{ __('Items') }}</h2>
            <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-white">
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
                    <tfoot class="border-t border-slate-200 text-sm">
                        @if ((float) $order->discount_amount > 0)
                            <tr><td colspan="3" class="px-4 py-2 text-right text-slate-600">{{ __('Discount') }}@if ($order->promotion_title) ({{ $order->promotion_title }})@endif</td><td class="px-4 py-2 text-right tabular-nums">−৳{{ number_format((float) $order->discount_amount, 2) }}</td></tr>
                        @endif
                        <tr><td colspan="3" class="px-4 py-2 text-right font-semibold">{{ __('To pay at the shop') }}</td><td class="px-4 py-2 text-right font-semibold tabular-nums">৳{{ number_format((float) $order->total_amount, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </section>

        @if ($order->customer_note)
            <p class="mt-4 text-sm"><span class="font-medium">{{ __('Your note') }}:</span> {{ $order->customer_note }}</p>
        @endif
        @if ($order->shop_note)
            <p class="mt-2 text-sm"><span class="font-medium">{{ __('From the shop') }}:</span> {{ $order->shop_note }}</p>
        @endif

        @if ($order->purchase)
            <p class="mt-4"><a href="{{ route('account.purchases.show', $order->purchase) }}" class="font-medium text-brand-700 hover:underline">{{ __('Open your receipt') }}</a></p>
        @endif

        @if ($open)
            <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-6">
                @csrf
                <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Cancel this order') }}</button>
            </form>
        @endif

        <section class="mt-8" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-semibold">{{ __('History') }}</h2>
            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($order->events as $event)
                    <li>{{ $event->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ \App\Modules\ShopOwnerOperations\Enums\OrderStatus::from($event->to_status)->label() }}@if ($event->note) · {{ $event->note }}@endif</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.customer>
