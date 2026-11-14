@php($isNew = ! $announcement->exists)
@php($local = fn ($moment) => $moment?->timezone($mall->timezone)->format('Y-m-d\TH:i'))

<x-layouts.customer :title="$isNew ? __('Write an announcement') : $announcement->title" :mall="$mall">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('mall-admin.announcements.index', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Announcements and events') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ $isNew ? __('Write an announcement') : __('Edit announcement') }}</h1>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <form method="POST" action="{{ $isNew ? route('mall-admin.announcements.store', $mall) : route('mall-admin.announcements.update', [$mall, $announcement]) }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <fieldset>
                <legend class="text-sm font-medium">{{ __('Kind') }}</legend>
                <div class="mt-1 flex gap-6 text-sm">
                    @foreach ($kinds as $kind)
                        <label class="flex items-center gap-2"><input type="radio" name="kind" value="{{ $kind->value }}" @checked(old('kind', $announcement->kind?->value) === $kind->value) class="accent-brand-600"> {{ $kind->label() }}</label>
                    @endforeach
                </div>
                @error('kind') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </fieldset>

            <x-form-field name="title" :label="__('Title')" :value="$announcement->title" required maxlength="150" />
            <div>
                <label for="body" class="block text-sm font-medium">{{ __('Text') }}</label>
                <textarea id="body" name="body" rows="6" required maxlength="5000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('body', $announcement->body) }}</textarea>
                @error('body') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="starts_at" type="datetime-local" :label="__('Starts (events: required)')" :value="$local($announcement->starts_at)" :hint="__('Mall time (:zone). A notice shows from this time.', ['zone' => $mall->timezone])" />
                <x-form-field name="ends_at" type="datetime-local" :label="__('Ends (optional)')" :value="$local($announcement->ends_at)" :hint="__('Shown until this time. An event without an end shows for 3 hours.')" />
            </div>
            <x-form-field name="location_note" :label="__('Where (events)')" :value="$announcement->location_note" maxlength="160" :placeholder="__('e.g. Atrium, ground floor')" />

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg border border-brand-600 px-5 py-2.5 font-medium text-brand-700 hover:bg-brand-50">{{ $isNew ? __('Save as draft') : __('Save') }}</button>
                @if ($isNew)
                    <button type="submit" name="publish" value="1" class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Publish now') }}</button>
                @endif
            </div>
        </form>
    </div>
</x-layouts.customer>
