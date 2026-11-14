@php($status = $maintenance->status->value)

<x-layouts.customer :title="$maintenance->title" :mall="$mall">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('mall-admin.maintenance.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Maintenance requests') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $maintenance->title }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $maintenance->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $maintenance->request_number }} · {{ $maintenance->shop->name }} · {{ $maintenance->category->label() }} · {{ $maintenance->priority->label() }} · {{ $maintenance->requestedBy->display_name }}</p>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif
        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <p class="mt-4 whitespace-pre-line rounded-xl border border-slate-200 bg-white p-4">{{ $maintenance->description }}</p>
        @if ($maintenance->resolution_note)
            <p class="mt-3 text-sm"><span class="font-medium">{{ __('What was done') }}:</span> {{ $maintenance->resolution_note }}</p>
        @endif

        <div class="mt-6 flex flex-wrap items-start gap-4">
            @if ($status === 'open')
                <form method="POST" action="{{ route('mall-admin.maintenance.acknowledge', [$mall, $maintenance]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Acknowledge') }}</button>
                </form>
            @endif
            @if ($status === 'acknowledged')
                <form method="POST" action="{{ route('mall-admin.maintenance.start', [$mall, $maintenance]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Start work') }}</button>
                </form>
            @endif
            @if ($status === 'in_progress')
                <form method="POST" action="{{ route('mall-admin.maintenance.resolve', [$mall, $maintenance]) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="resolve-note" class="block text-xs font-medium">{{ __('What was done (the shop sees this)') }}</label>
                        <input id="resolve-note" name="note" required minlength="3" maxlength="500" class="mt-1 w-80 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Mark fixed') }}</button>
                </form>
            @endif
        </div>

        <section class="mt-8" aria-labelledby="orders-heading">
            <h2 id="orders-heading" class="font-semibold">{{ __('Work orders') }}</h2>
            @if ($orders->isEmpty())
                <p class="mt-2 text-sm text-slate-600">{{ __('No work order yet.') }}</p>
            @else
                <ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-sm">
                    @foreach ($orders as $order)
                        <li class="flex items-center justify-between gap-3 px-4 py-2">
                            <a href="{{ route('mall-admin.work-orders.show', [$mall, $order]) }}" class="font-medium text-brand-700 hover:underline">{{ $order->work_order_number }} · {{ $order->title }}</a>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $order->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($openOrder === null && in_array($status, ['open', 'acknowledged', 'in_progress'], true))
                <form method="POST" action="{{ route('mall-admin.maintenance.work-order', [$mall, $maintenance]) }}" class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3 sm:items-end">
                    @csrf
                    <h3 class="font-medium sm:col-span-3">{{ __('Create a work order for this request') }}</h3>
                    <div>
                        <label for="wo-floor" class="block text-xs font-medium">{{ __('Floor (optional)') }}</label>
                        <select id="wo-floor" name="floor_id" class="mt-1 w-full rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                            <option value="">{{ __('Not specified') }}</option>
                            @foreach ($floors as $floor)
                                <option value="{{ $floor->floor_id }}">{{ __($floor->name) }} ({{ $floor->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="wo-location" class="block text-xs font-medium">{{ __('Where exactly (optional)') }}</label>
                        <input id="wo-location" name="location_note" maxlength="160" class="mt-1 w-full rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <div>
                        <label for="wo-due" class="block text-xs font-medium">{{ __('Due date (optional)') }}</label>
                        <input id="wo-due" name="due_on" type="date" class="mt-1 w-full rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50 sm:col-span-3 sm:w-fit">{{ __('Create work order') }}</button>
                </form>
            @endif
        </section>

        <section class="mt-8" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-semibold">{{ __('History') }}</h2>
            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($maintenance->events as $event)
                    <li>{{ $event->created_at->timezone($mall->timezone)->format('d M Y H:i') }} · {{ \App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus::from($event->to_status)->label() }} · {{ $event->actor?->display_name ?? __('System') }}@if ($event->note) · {{ $event->note }}@endif</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.customer>
