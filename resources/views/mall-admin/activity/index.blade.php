<x-layouts.customer :title="__('Activity log').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Activity log') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Every sensitive change in this mall is recorded here and cannot be edited or deleted. Times are shown in Asia/Dhaka.') }}</p>

        <form method="GET" action="{{ route('mall-admin.activity.index', $mall) }}" class="mt-6 flex flex-wrap items-end gap-3">
            <div>
                <label for="area" class="block text-xs font-medium">{{ __('Area') }}</label>
                <select id="area" name="area" class="mt-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
                    <option value="">{{ __('Everything') }}</option>
                    @foreach ($areas as $option)
                        <option value="{{ $option }}" @selected($area === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Filter') }}</button>
        </form>

        @if ($entries->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('Nothing recorded yet.') }}</p>
        @else
            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('When') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Who') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('What') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2">{{ $entry->occurred_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td>
                                <td class="px-4 py-2">{{ $entry->actor_name ?? ($entry->actor_id === null ? __('System') : __('Removed account')) }}</td>
                                <td class="px-4 py-2 font-medium">{{ $entry->action }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ collect($entry->metadata ?? [])->map(fn ($value, $key) => $key.': '.(is_scalar($value) ? $value : json_encode($value)))->join(' · ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $entries->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
