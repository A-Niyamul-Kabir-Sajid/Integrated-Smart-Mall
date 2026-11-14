<x-layouts.customer :title="__('Orders').' · '.$shop->name">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Orders') }}</h1>
            <a href="{{ route('workspace.sales.index', $shop) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Counter sales') }}</a>
        </div>

        <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="{{ __('Order status') }}">
            <x-nav-link :href="route('workspace.orders.index', $shop)" :active="$status === null">{{ __('All') }}</x-nav-link>
            @foreach ($statuses as $option)
                <x-nav-link :href="route('workspace.orders.index', [$shop, 'status' => $option->value])" :active="$status === $option->value">{{ $option->label() }} ({{ $counts->get($option->value, 0) }})</x-nav-link>
            @endforeach
        </nav>

        @if ($orders->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No orders here yet.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Order') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Customer') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Collect by') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ __('Total') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($orders as $order)
                            <tr>
                                <td class="px-4 py-2"><a href="{{ route('workspace.orders.show', [$shop, $order]) }}" class="font-medium text-brand-700 hover:underline">{{ $order->order_number }}</a></td>
                                <td class="px-4 py-2">{{ $order->customer->display_name }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $order->pickup_by->timezone('Asia/Dhaka')->format('d M H:i') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $order->total_amount, 2) }}</td>
                                <td class="px-4 py-2">{{ $order->status->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
