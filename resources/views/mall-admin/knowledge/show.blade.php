<x-layouts.customer :title="$document->title.' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('assistant.admin.knowledge.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Assistant knowledge') }}</a>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">{{ $document->title }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __($document->source_kind->label()) }} · {{ $document->isSystemManaged() ? __('Captured from mall records') : __('Written by administrators') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-emerald-50 text-emerald-800' => $document->access_scope->value === 'public', 'bg-amber-50 text-amber-900' => $document->access_scope->value === 'mall_admin'])>{{ __($document->access_scope->label()) }}</span>
                <span @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-slate-100 text-slate-700' => $document->status->value === 'active', 'bg-rose-50 text-rose-800' => $document->status->value === 'withdrawn'])>{{ $document->status->value === 'active' ? __('Active') : __('Withdrawn') }}</span>
            </div>
        </div>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if ($document->isSystemManaged())
            <p class="mt-4 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700" role="note">{{ __('This document is captured from the mall records. Change the shop, product or facility instead.') }}</p>
        @else
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('assistant.admin.knowledge.edit', [$mall, $document]) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Edit') }}</a>
                @if ($document->status->value === 'active')
                    <form method="POST" action="{{ route('assistant.admin.knowledge.withdraw', [$mall, $document]) }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ __('Withdraw') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('assistant.admin.knowledge.restore', [$mall, $document]) }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('Make active again') }}</button>
                    </form>
                    <form method="POST" action="{{ route('assistant.admin.knowledge.destroy', [$mall, $document]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">{{ __('Delete for good') }}</button>
                    </form>
                @endif
            </div>
        @endif

        <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <dt class="text-xs uppercase text-slate-500">{{ __('Source') }}</dt>
                <dd class="mt-1 break-words">{{ str_starts_with((string) $document->source_locator, 'manual:') ? __('Written here') : $document->source_locator }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <dt class="text-xs uppercase text-slate-500">{{ __('Current revision') }}</dt>
                <dd class="mt-1">{{ $document->currentRevision?->revision_no ?? '—' }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <dt class="text-xs uppercase text-slate-500">{{ __('Captured') }}</dt>
                <dd class="mt-1">{{ $document->currentRevision?->captured_at->timezone($mall->timezone)->format('d M Y H:i') ?? '—' }}</dd>
            </div>
        </dl>

        <section class="mt-8" aria-labelledby="chunks-heading">
            <h2 id="chunks-heading" class="text-lg font-semibold">{{ __('What the assistant can quote') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('The current text, in the pieces the assistant retrieves. Each piece is quoted with this document as its source.') }}</p>
            <ol class="mt-3 space-y-3">
                @foreach ($document->currentRevision?->chunks ?? [] as $chunk)
                    <li class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                        <p class="text-xs uppercase text-slate-500">{{ __('Piece :number', ['number' => $chunk->chunk_no + 1]) }}@if ($chunk->heading) · {{ $chunk->heading }}@endif</p>
                        <p class="mt-1 whitespace-pre-line text-slate-800">{{ $chunk->chunk_text }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="mt-8" aria-labelledby="revisions-heading">
            <h2 id="revisions-heading" class="text-lg font-semibold">{{ __('Revisions') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Earlier revisions are kept so that answers given from them can still show their source.') }}</p>
            <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Revision') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Captured') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Pieces') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Fingerprint') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($document->revisions as $revision)
                            <tr>
                                <td class="px-4 py-2">{{ $revision->revision_no }}@if ($revision->revision_id === $document->current_revision_id) <span class="ml-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">{{ __('current') }}</span>@endif</td>
                                <td class="px-4 py-2">{{ $revision->captured_at->timezone($mall->timezone)->format('d M Y H:i') }}</td>
                                <td class="px-4 py-2">{{ $revision->chunks_count }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ substr($revision->content_hash, 0, 12) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.customer>
