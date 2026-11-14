<x-layouts.customer :title="__('Shops and tenants').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Shops and tenants') }}</h1>
            <a href="{{ route('mall-admin.shops.create', $mall) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Add a shop') }}</a>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ __('Applications from customers are decided on the shop applications page; suspension is done there too.') }}
            <a href="{{ route('shop-applications.admin.index', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Shop applications') }}</a></p>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif

        <form method="GET" action="{{ route('mall-admin.shops.index', $mall) }}" class="mt-6 flex flex-wrap items-end gap-3" role="search">
            <div>
                <label for="q" class="block text-xs font-medium">{{ __('Shop name') }}</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100" class="mt-1 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-300">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium">{{ __('Status') }}</label>
                <select id="status" name="status" class="mt-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Filter') }}</button>
        </form>

        @if ($shops->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No shops match.') }}</p>
        @else
            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Shop') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Category') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Unit') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($shops as $shop)
                            <tr @class(['opacity-60' => $shop->status->value === 'archived'])>
                                <td class="px-4 py-2 font-medium">{{ $shop->name }}</td>
                                <td class="px-4 py-2">{{ $shop->category ? __($shop->category->name) : '—' }}</td>
                                <td class="px-4 py-2">{{ $shop->units->map(fn ($unit) => $unit->floor->code.' '.$unit->unit_code)->join(', ') ?: '—' }}</td>
                                <td class="px-4 py-2">{{ ucfirst($shop->status->value) }}</td>
                                <td class="px-4 py-2 text-right"><a href="{{ route('mall-admin.shops.edit', [$mall, $shop]) }}" class="font-medium text-brand-700 hover:underline" aria-label="{{ __('Edit :name', ['name' => $shop->name]) }}">{{ __('Edit') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $shops->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
