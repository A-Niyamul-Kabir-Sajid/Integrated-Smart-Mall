@php($status = $order->status->value)

<x-layouts.customer :title="$order->title" :mall="$mall">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('mall-admin.work-orders.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Work orders') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $order->title }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $order->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $order->work_order_number }} · {{ $order->priority->label() }}@if ($order->floor) · {{ __($order->floor->name) }}@endif @if ($order->location_note) · {{ $order->location_note }}@endif @if ($order->due_on) · {{ __('due :date', ['date' => $order->due_on->format('d M Y')]) }}@endif</p>
        @if ($order->maintenanceRequest)
            <p class="text-sm text-slate-600">{{ __('Raised from') }} <a href="{{ route('mall-admin.maintenance.show', [$mall, $order->maintenanceRequest]) }}" class="font-medium text-brand-700 hover:underline">{{ $order->maintenanceRequest->request_number }}</a> · {{ $order->shop?->name }}</p>
        @endif

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif
        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <p class="mt-4 whitespace-pre-line rounded-xl border border-slate-200 bg-white p-4">{{ $order->description }}</p>
        <p class="mt-2 text-sm text-slate-600">{{ __('Assigned to') }}: <span class="font-medium">{{ $order->assignee?->display_name ?? __('nobody yet') }}</span></p>
        @if ($order->completion_note)
            <p class="mt-2 text-sm"><span class="font-medium">{{ __('What was done') }}:</span> {{ $order->completion_note }}</p>
        @endif

        @if (in_array($status, ['open', 'assigned', 'in_progress'], true))
            <form method="POST" action="{{ route('mall-admin.work-orders.assign', [$mall, $order]) }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
                @csrf
                <div>
                    <label for="assignee" class="block text-xs font-medium">{{ $status === 'open' ? __('Assign to') : __('Reassign to') }}</label>
                    <select id="assignee" name="assignee" required class="mt-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                        @foreach ($administrators as $administrator)
                            <option value="{{ $administrator->user_id }}" @selected($order->assigned_to_user_id === $administrator->user_id)>{{ $administrator->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="due_on" class="block text-xs font-medium">{{ __('Due date (optional)') }}</label>
                    <input id="due_on" name="due_on" type="date" value="{{ $order->due_on?->format('Y-m-d') }}" class="mt-1 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                </div>
                <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Assign') }}</button>
            </form>
        @endif

        <div class="mt-4 flex flex-wrap items-start gap-4">
            @if ($status === 'assigned')
                <form method="POST" action="{{ route('mall-admin.work-orders.start', [$mall, $order]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Start work') }}</button>
                </form>
            @endif
            @if ($status === 'in_progress')
                <form method="POST" action="{{ route('mall-admin.work-orders.complete', [$mall, $order]) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="complete-note" class="block text-xs font-medium">{{ __('What was done') }}</label>
                        <input id="complete-note" name="note" required minlength="3" maxlength="500" class="mt-1 w-80 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Complete') }}</button>
                </form>
            @endif
            @if (in_array($status, ['open', 'assigned', 'in_progress'], true))
                <form method="POST" action="{{ route('mall-admin.work-orders.cancel', [$mall, $order]) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="cancel-reason" class="block text-xs font-medium">{{ __('Reason for cancelling') }}</label>
                        <input id="cancel-reason" name="reason" required minlength="5" maxlength="500" class="mt-1 w-72 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
                    </div>
                    <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50">{{ __('Cancel work order') }}</button>
                </form>
            @endif
        </div>

        <section class="mt-8" aria-labelledby="history-heading">
            <h2 id="history-heading" class="font-semibold">{{ __('History') }}</h2>
            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($order->events as $event)
                    <li>{{ $event->created_at->timezone($mall->timezone)->format('d M Y H:i') }} · {{ \App\Modules\MallOwnerAdmin\Enums\WorkOrderStatus::from($event->to_status)->label() }} · {{ $event->actor?->display_name ?? __('System') }}@if ($event->note) · {{ $event->note }}@endif</li>
                @endforeach
            </ol>
        </section>
    </div>
</x-layouts.customer>
