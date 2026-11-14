<x-layouts.customer :title="__('Your returns')">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('account.purchases.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Purchases and receipts') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Your returns and exchanges') }}</h1>

        @if ($returns->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('You have not asked to return anything. Open a purchase to start a request.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($returns as $return)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('returns.show', $return) }}" class="font-medium text-brand-700 hover:underline">{{ $return->return_number }}</a>
                            <span class="text-sm text-slate-600">· {{ $return->shop->name }} · {{ $return->kind->label() }} · ৳{{ number_format((float) $return->value_amount, 2) }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $return->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $returns->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
