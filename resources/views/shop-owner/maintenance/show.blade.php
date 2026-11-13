@php($status = $maintenance->status->value)

<x-layouts.customer :title="$maintenance->title.' · '.$shop->name">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('workspace.maintenance.index', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Maintenance requests') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $maintenance->title }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $maintenance->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $maintenance->request_number }} · {{ $maintenance->category->label() }} · {{ $maintenance->priority->label() }} · {{ $maintenance->requestedBy->display_name }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <p class="mt-4 whitespace-pre-line rounded-xl border border-slate-200 bg-white p-4">{{ $maintenance->description }}</p>
        @if ($maintenance->resolution_note)
            <p class="mt-3 text-sm"><span class="font-medium">{{ __('What the mall did') }}:</span> {{ $maintenance->resolution_note }}</p>
        @endif

        <div class="mt-6 flex flex-wrap items-end gap-3">
            @if ($status === 'resolved')
                <form method="POST" action="{{ route('workspace.maintenance.confirm', [$shop, $maintenance]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('It is fixed, close the request') }}</button>
                </form>
                <form method="POST" action="{{ route('workspace.maintenance.reopen', [$shop, $maintenance]) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="reopen-note" class="block text-xs font-medium">{{ __('Still wrong? Say what') }}</label>
                        <input id="reopen-note" name="note" required minlength="3" maxlength="500" class="mt-1 w-64 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Reopen') }}</button>
                </form>
            @endif
            @if (in_array($status, ['open', 'acknowledged'], true))
                <form method="POST" action="{{ route('workspace.maintenance.cancel', [$shop, $maintenance]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Cancel this request') }}</button>
                </form>
            @endif
        </div>

        <section class="mt-8" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-semibold">{{ __('History') }}</h2>
            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($maintenance->events as $event)
                    <li>{{ $event->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ \App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus::from($event->to_status)->label() }} · {{ $event->actor?->display_name ?? __('System') }}@if ($event->note) · {{ $event->note }}@endif</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.customer>
