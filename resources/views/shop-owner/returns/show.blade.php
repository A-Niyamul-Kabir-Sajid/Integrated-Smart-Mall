@php($status = $return->status->value)

<x-layouts.customer :title="__('Return :number', ['number' => $return->return_number]).' · '.$shop->name">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('workspace.returns.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Returns') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $return->return_number }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $return->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $return->kind->label() }} · {{ __('Sale') }} <a href="{{ route('workspace.sales.show', [$shop, $return->purchase]) }}" class="text-brand-700 hover:underline">{{ $return->purchase->external_receipt_ref }}</a> · {{ $return->requestedBy->display_name }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-sm">
            @foreach ($return->items as $item)
                <li class="flex justify-between gap-3 px-4 py-2"><span>{{ $item->purchaseItem->product_label_snapshot }} × {{ $item->quantity }}</span><span class="tabular-nums">৳{{ number_format((float) $item->value_amount, 2) }}</span></li>
            @endforeach
            <li class="flex justify-between gap-3 px-4 py-2 font-semibold"><span>{{ $return->kind->value === 'return' ? __('Refund to hand back') : __('Value of the goods') }}</span><span class="tabular-nums">৳{{ number_format((float) $return->value_amount, 2) }}</span></li>
        </ul>

        <p class="mt-4 text-sm"><span class="font-medium">{{ __('Reason') }}:</span> {{ $return->reason }}</p>
        @if ($return->decision_note)
            <p class="mt-2 text-sm"><span class="font-medium">{{ __('Decision note') }}:</span> {{ $return->decision_note }}</p>
        @endif
        @if ($status === 'completed')
            <p class="mt-2 text-sm text-slate-600">{{ $return->restocked ? __('The goods were put back into stock.') : __('The goods were not put back into stock.') }}</p>
        @endif

        @if ($return->status->isOpen())
            <section class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="actions-heading">
                <h2 id="actions-heading" class="font-semibold">{{ __('Actions') }}</h2>
                <div class="flex flex-wrap items-end gap-3">
                    @if ($status === 'requested')
                        <form method="POST" action="{{ route('workspace.returns.approve', [$shop, $return]) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div>
                                <label for="approve-note" class="block text-xs font-medium">{{ __('Note for the customer (optional)') }}</label>
                                <input id="approve-note" name="note" maxlength="500" class="mt-1 w-64 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                            </div>
                            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Approve') }}</button>
                        </form>
                        <form method="POST" action="{{ route('workspace.returns.reject', [$shop, $return]) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div>
                                <label for="reject-reason" class="block text-xs font-medium">{{ __('Reason for declining') }}</label>
                                <input id="reject-reason" name="reason" required minlength="3" maxlength="500" class="mt-1 w-64 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                            </div>
                            <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Decline') }}</button>
                        </form>
                    @endif
                    @if ($status === 'approved')
                        <form method="POST" action="{{ route('workspace.returns.complete', [$shop, $return]) }}" class="flex flex-wrap items-center gap-3">
                            @csrf
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="restock" value="1" checked class="size-4 accent-brand-600"> {{ __('Put the goods back into stock') }}</label>
                            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Goods received, complete') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('workspace.returns.cancel', [$shop, $return]) }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Cancel') }}</button>
                    </form>
                </div>
            </section>
        @endif

        <section class="mt-8" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-semibold">{{ __('History') }}</h2>
            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($return->events as $event)
                    <li>{{ $event->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ \App\Modules\ShopOwnerOperations\Enums\ReturnStatus::from($event->to_status)->label() }} · {{ $event->actor?->display_name ?? __('System') }}@if ($event->note) · {{ $event->note }}@endif</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.customer>
