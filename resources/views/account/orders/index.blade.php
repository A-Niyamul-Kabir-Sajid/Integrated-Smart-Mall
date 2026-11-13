<x-layouts.customer :title="__('Your orders')">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('account.show') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your account') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Your orders') }}</h1>
            <a href="{{ route('account.purchases.index') }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Purchases and receipts') }}</a>
        </div>

        @if ($orders->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('You have not ordered anything yet. Open a shop page and choose Order from this shop.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($orders as $order)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('orders.show', $order) }}" class="font-medium text-brand-700 hover:underline">{{ $order->order_number }}</a>
                            <span class="text-sm text-slate-600">· {{ $order->shop->name }} · {{ trans_choice(':count item|:count items', $order->items_count, ['count' => $order->items_count]) }} · ৳{{ number_format((float) $order->total_amount, 2) }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $order->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
