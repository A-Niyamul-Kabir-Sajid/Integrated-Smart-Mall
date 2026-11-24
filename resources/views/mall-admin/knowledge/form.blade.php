@php($isNew = ! $document->exists)

<x-layouts.customer :title="($isNew ? __('Add document') : $document->title).' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ $isNew ? route('assistant.admin.knowledge.index', $mall) : route('assistant.admin.knowledge.show', [$mall, $document]) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $isNew ? __('Assistant knowledge') : $document->title }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ $isNew ? __('Add document') : __('Edit :name', ['name' => $document->title]) }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('The assistant quotes this text word for word and names it as the source. Write facts a visitor may rely on, and keep them up to date.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" enctype="multipart/form-data" action="{{ $isNew ? route('assistant.admin.knowledge.store', $mall) : route('assistant.admin.knowledge.update', [$mall, $document]) }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <x-form-field name="title" :label="__('Title')" :value="$document->title" required maxlength="200" :placeholder="__('e.g. Visitor questions and answers')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="source_kind" class="block text-sm font-medium">{{ __('Kind') }}</label>
                    <select id="source_kind" name="source_kind" required class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                        @foreach ($kinds as $kind)
                            <option value="{{ $kind->value }}" @selected(old('source_kind', $document->source_kind?->value) === $kind->value)>{{ __($kind->label()) }}</option>
                        @endforeach
                    </select>
                    @error('source_kind') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <fieldset>
                    <legend class="text-sm font-medium">{{ __('Who may be told') }}</legend>
                    <div class="mt-2 space-y-1 text-sm">
                        @foreach ($scopes as $scope)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="access_scope" value="{{ $scope->value }}" @checked(old('access_scope', $document->access_scope?->value) === $scope->value) required class="accent-brand-600">
                                {{ __($scope->label()) }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('An internal note is always for mall administrators only, whatever is chosen here.') }}</p>
                    @error('access_scope') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </fieldset>
            </div>

            <x-form-field name="source_reference" :label="__('Where this text comes from (optional)')" :value="$isNew || str_starts_with((string) $document->source_locator, 'manual:') ? null : $document->source_locator" maxlength="500" :placeholder="__('e.g. Visitor handbook 2026, page 4')" />

            <div>
                <label for="content" class="block text-sm font-medium">{{ __('Text') }}</label>
                <textarea id="content" name="content" rows="16" maxlength="{{ (int) config('assistant.max_document_characters') }}" aria-describedby="content-hint" @error('content') aria-invalid="true" @enderror class="mt-1 w-full rounded-lg bg-white px-3 py-2 font-mono text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('content', $content) }}</textarea>
                <p id="content-hint" class="mt-1 text-xs text-slate-500">{{ __('Plain text. Start a line with "#" for a section heading. For questions and answers write "Q: the question" and on the next line "A: the answer". Each section is quoted on its own, so keep one subject per section.') }}</p>
                @error('content') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="file" class="block text-sm font-medium">{{ __('Or upload a text file') }}</label>
                <input id="file" name="file" type="file" accept=".txt,.md,text/plain,text/markdown" aria-describedby="file-hint" class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:font-medium file:text-brand-700">
                <p id="file-hint" class="mt-1 text-xs text-slate-500">{{ __('A .txt or .md file in UTF-8, at most 512 KB. An uploaded file replaces the text above.') }}</p>
                @error('file') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ $isNew ? __('Add document') : __('Save document') }}</button>
        </form>
    </div>
</x-layouts.customer>
