<x-layouts.customer :title="__('Return :number', ['number' => $return->return_number])">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('returns.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your returns') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $return->return_number }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $return->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $return->shop->name }} · {{ $return->kind->label() }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-sm">
            @foreach ($return->items as $item)
                <li class="flex justify-between gap-3 px-4 py-2"><span>{{ $item->purchaseItem->product_label_snapshot }} × {{ $item->quantity }}</span><span class="tabular-nums">৳{{ number_format((float) $item->value_amount, 2) }}</span></li>
            @endforeach
            <li class="flex justify-between gap-3 px-4 py-2 font-semibold"><span>{{ $return->kind->value === 'return' ? __('Refund at the shop') : __('Value of the goods') }}</span><span class="tabular-nums">৳{{ number_format((float) $return->value_amount, 2) }}</span></li>
        </ul>

        <p class="mt-4 text-sm"><span class="font-medium">{{ __('Your reason') }}:</span> {{ $return->reason }}</p>
        @if ($return->decision_note)
            <p class="mt-2 text-sm"><span class="font-medium">{{ __('From the shop') }}:</span> {{ $return->decision_note }}</p>
        @endif
        <p class="mt-2 text-xs text-slate-500">{{ __('The refund is handed back at the shop counter; nothing is paid or refunded through this site.') }}</p>

        @if ($return->status->isOpen())
            <form method="POST" action="{{ route('returns.cancel', $return) }}" class="mt-6">
                @csrf
                <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Cancel this request') }}</button>
            </form>
        @endif

        <section class="mt-8" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-semibold">{{ __('History') }}</h2>
            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($return->events as $event)
                    <li>{{ $event->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ \App\Modules\ShopOwnerOperations\Enums\ReturnStatus::from($event->to_status)->label() }}@if ($event->note) · {{ $event->note }}@endif</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.customer>
