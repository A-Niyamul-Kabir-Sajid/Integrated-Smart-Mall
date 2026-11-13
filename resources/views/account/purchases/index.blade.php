<x-layouts.customer :title="__('Your purchases')">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('account.show') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your account') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Your purchases and receipts') }}</h1>
            <a href="{{ route('returns.index') }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Returns and exchanges') }}</a>
        </div>

        @if ($purchases->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No purchases are linked to your account yet.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($purchases as $purchase)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('account.purchases.show', $purchase) }}" class="font-medium text-brand-700 hover:underline">{{ $purchase->shop->name }}</a>
                            <span class="text-sm text-slate-600">· {{ $purchase->purchased_at->timezone('Asia/Dhaka')->format('d M Y') }} · {{ trans_choice(':count item|:count items', $purchase->items_count, ['count' => $purchase->items_count]) }} · ৳{{ number_format((float) $purchase->total_amount, 2) }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ ucfirst($purchase->status->value) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $purchases->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
