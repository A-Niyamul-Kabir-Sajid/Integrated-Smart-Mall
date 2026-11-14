<x-layouts.customer :title="__('Counter sales').' · '.$shop->name">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Counter sales') }}</h1>
            <div class="flex gap-2">
                <a href="{{ route('workspace.orders.index', $shop) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Orders') }}</a>
                <a href="{{ route('workspace.sales.create', $shop) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Record a sale') }}</a>
            </div>
        </div>

        @if ($purchases->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No sales recorded yet.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Receipt ref') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('When') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ __('Lines') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ __('Total') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($purchases as $purchase)
                            <tr>
                                <td class="px-4 py-2"><a href="{{ route('workspace.sales.show', [$shop, $purchase]) }}" class="font-medium text-brand-700 hover:underline">{{ $purchase->external_receipt_ref }}</a></td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $purchase->purchased_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $purchase->items_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $purchase->total_amount, 2) }}</td>
                                <td class="px-4 py-2">{{ ucfirst($purchase->status->value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $purchases->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
