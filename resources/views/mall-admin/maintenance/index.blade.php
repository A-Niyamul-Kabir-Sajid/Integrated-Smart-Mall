<x-layouts.customer :title="__('Maintenance requests')" :mall="$mall">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Maintenance requests') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Problems reported by shops, urgent first. Acknowledge a request, then fix it through a work order or mark it fixed directly; the shop confirms.') }}</p>

        <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="{{ __('Request status') }}">
            <x-nav-link :href="route('mall-admin.maintenance.index', $mall)" :active="$status === null">{{ __('All') }}</x-nav-link>
            @foreach ($statuses as $option)
                <x-nav-link :href="route('mall-admin.maintenance.index', [$mall, 'status' => $option->value])" :active="$status === $option->value">{{ $option->label() }}</x-nav-link>
            @endforeach
        </nav>

        @if ($requests->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No requests here.') }}</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($requests as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('mall-admin.maintenance.show', [$mall, $item]) }}" class="font-medium text-brand-700 hover:underline">{{ $item->title }}</a>
                            <span class="block text-sm text-slate-600">{{ $item->request_number }} · {{ $item->shop->name }} · {{ $item->category->label() }} · {{ $item->priority->label() }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $item->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $requests->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
