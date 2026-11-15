<x-layouts.customer :title="__('Assistant knowledge').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <p class="text-sm text-slate-500">{{ __('Mall administration') }}</p>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $mall->name }} · {{ __('Assistant knowledge') }}</h1>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('assistant.admin.knowledge.sync', $mall) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Read mall records again') }}</button>
                </form>
                <a href="{{ route('assistant.admin.knowledge.create', $mall) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Add document') }}</a>
            </div>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ __('The assistant answers only from these documents and from the live catalogue. Documents you write (questions and answers, guides, internal notes) are yours to edit; shop profiles, product offers and the floor guide are read from the mall records every hour.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="GET" action="{{ route('assistant.admin.knowledge.index', $mall) }}" class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5" role="search" aria-label="{{ __('Filter documents') }}">
            <div class="lg:col-span-2">
                <label for="knowledge-q" class="text-xs font-medium text-slate-600">{{ __('Title contains') }}</label>
                <input id="knowledge-q" name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="knowledge-kind" class="text-xs font-medium text-slate-600">{{ __('Kind') }}</label>
                <select id="knowledge-kind" name="kind" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('All kinds') }}</option>
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->value }}" @selected(($filters['kind'] ?? null) === $kind->value)>{{ __($kind->label()) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="knowledge-status" class="text-xs font-medium text-slate-600">{{ __('Status') }}</label>
                <select id="knowledge-status" name="status" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('Any status') }}</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>{{ __('Active') }}</option>
                    <option value="withdrawn" @selected(($filters['status'] ?? null) === 'withdrawn')>{{ __('Withdrawn') }}</option>
                </select>
            </div>
            <div>
                <label for="knowledge-origin" class="text-xs font-medium text-slate-600">{{ __('Origin') }}</label>
                <select id="knowledge-origin" name="origin" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    <option value="">{{ __('Written and captured') }}</option>
                    <option value="written" @selected(($filters['origin'] ?? null) === 'written')>{{ __('Written by administrators') }}</option>
                    <option value="captured" @selected(($filters['origin'] ?? null) === 'captured')>{{ __('Captured from mall records') }}</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700 sm:col-span-2 lg:col-span-5 lg:justify-self-start">{{ __('Filter') }}</button>
        </form>

        @if ($documents->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No document matches. Add one, or read the mall records.') }}</p>
        @else
            <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Title') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Kind') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Who may be told') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Revision') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($documents as $document)
                            <tr @class(['opacity-60' => $document->status->value === 'withdrawn'])>
                                <td class="px-4 py-2">
                                    <a href="{{ route('assistant.admin.knowledge.show', [$mall, $document]) }}" class="font-medium text-brand-700 hover:underline">{{ $document->title }}</a>
                                    <span class="block text-xs text-slate-500">{{ $document->isSystemManaged() ? __('Captured from mall records') : __('Written by administrators') }}</span>
                                </td>
                                <td class="px-4 py-2">{{ __($document->source_kind->label()) }}</td>
                                <td class="px-4 py-2">
                                    <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $document->access_scope->value === 'public', 'bg-amber-50 text-amber-900' => $document->access_scope->value === 'mall_admin'])>{{ __($document->access_scope->label()) }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($document->currentRevision)
                                        {{ $document->currentRevision->revision_no }}
                                        <span class="block text-xs text-slate-500">{{ $document->currentRevision->captured_at->timezone($mall->timezone)->format('d M Y H:i') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $document->status->value === 'active' ? __('Active') : __('Withdrawn') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $documents->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
