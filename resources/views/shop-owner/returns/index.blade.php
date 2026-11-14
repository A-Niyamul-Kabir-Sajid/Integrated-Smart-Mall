<x-layouts.customer :title="__('Returns').' · '.$shop->name">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Returns and exchanges') }}</h1>

        <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="{{ __('Return status') }}">
            <x-nav-link :href="route('workspace.returns.index', $shop)" :active="$status === null">{{ __('All') }}</x-nav-link>
            @foreach ($statuses as $option)
                <x-nav-link :href="route('workspace.returns.index', [$shop, 'status' => $option->value])" :active="$status === $option->value">{{ $option->label() }}</x-nav-link>
            @endforeach
        </nav>

        @if ($returns->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No returns here yet.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Return') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Sale') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Kind') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ __('Value') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($returns as $return)
                            <tr>
                                <td class="px-4 py-2"><a href="{{ route('workspace.returns.show', [$shop, $return]) }}" class="font-medium text-brand-700 hover:underline">{{ $return->return_number }}</a></td>
                                <td class="px-4 py-2">{{ $return->purchase->external_receipt_ref }}</td>
                                <td class="px-4 py-2">{{ $return->kind->value === 'return' ? __('Return') : __('Exchange') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">৳{{ number_format((float) $return->value_amount, 2) }}</td>
                                <td class="px-4 py-2">{{ $return->status->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $returns->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
