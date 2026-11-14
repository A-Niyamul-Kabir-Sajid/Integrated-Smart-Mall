<x-layouts.customer :title="__('Work orders')" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Work orders') }}</h1>
            <a href="{{ route('mall-admin.work-orders.create', $mall) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('New work order') }}</a>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ __('Jobs for the mall\'s own maintenance team, either for a common area or raised from a shop\'s request.') }}</p>

        <form method="GET" action="{{ route('mall-admin.work-orders.index', $mall) }}" class="mt-6 flex flex-wrap items-end gap-3">
            <div>
                <label for="status" class="block text-xs font-medium">{{ __('Status') }}</label>
                <select id="status" name="status" class="mt-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}" @selected(($filters['status'] ?? null) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="mine" value="1" @checked(! empty($filters['mine'])) class="size-4 accent-brand-600"> {{ __('Assigned to me') }}</label>
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Filter') }}</button>
        </form>

        @if ($orders->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No work orders here.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($orders as $order)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('mall-admin.work-orders.show', [$mall, $order]) }}" class="font-medium text-brand-700 hover:underline">{{ $order->title }}</a>
                            <span class="block text-sm text-slate-600">{{ $order->work_order_number }} · {{ $order->priority->label() }}@if ($order->floor) · {{ __($order->floor->name) }}@endif @if ($order->shop) · {{ $order->shop->name }}@endif · {{ $order->assignee?->display_name ?? __('unassigned') }}@if ($order->due_on) · {{ __('due :date', ['date' => $order->due_on->format('d M Y')]) }}@endif</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $order->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
