<x-layouts.customer :title="__('Assistant').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        <div>
            <p class="text-sm text-slate-500">{{ __('Mall administration') }}</p>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ $mall->name }} · {{ __('Assistant') }}</h1>
                @if ($hasConversation)
                    <form method="POST" action="{{ route('assistant.admin.destroy', $mall) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ __('Delete this conversation') }}</button>
                    </form>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-600">{{ __('Ask for a figure of the metric catalogue for a period, a draft of a monthly report, or what the mall\'s documents say. Figures come from the same records as the dashboards; nothing is estimated.') }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('The assistant cannot change anything. To act on an answer, use the workspace page it links to.') }}</p>
            <ul class="mt-3 flex flex-wrap gap-3 text-sm">
                <li><a href="{{ route('assistant.admin.knowledge.index', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Assistant knowledge') }}</a></li>
                <li><a href="{{ route('assistant.admin.activity', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Assistant activity') }}</a></li>
                @if (Route::has('mall-admin.dashboard'))
                    <li><a href="{{ route('mall-admin.dashboard', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Mall workspace') }}</a></li>
                @endif
            </ul>
        </div>

        @if ($messages === [])
            <section class="rounded-xl border border-dashed border-slate-300 bg-white p-6" aria-labelledby="examples-heading">
                <h2 id="examples-heading" class="font-semibold">{{ __('Try asking') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($suggestions as $suggestion)
                        <li>
                            <form method="POST" action="{{ route('assistant.admin.messages.store', $mall) }}">
                                @csrf
                                <input type="hidden" name="message" value="{{ $suggestion }}">
                                <button type="submit" class="rounded-full border border-brand-600 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ $suggestion }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @else
            <ol class="space-y-4" role="log" aria-label="{{ __('Conversation') }}">
                @foreach ($messages as $message)
                    <x-assistant-message :message="$message" :latest="$loop->last" />
                @endforeach
            </ol>
        @endif

        <form method="POST" action="{{ route('assistant.admin.messages.store', $mall) }}" class="rounded-xl border border-slate-200 bg-white p-4">
            @csrf
            <label for="assistant-message" class="block text-sm font-medium">{{ __('Your question') }}</label>
            <div class="mt-1 flex gap-2">
                <input id="assistant-message" name="message" type="text" required maxlength="{{ (int) config('assistant.max_message_length') }}" value="{{ old('message') }}" autocomplete="off" @if ($messages !== []) autofocus @endif
                    aria-describedby="assistant-note" @error('message') aria-invalid="true" @enderror
                    placeholder="{{ __('e.g. Complaints by category in October') }}"
                    class="min-w-0 flex-1 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">{{ __('Ask') }}</button>
            </div>
            @error('message') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            <p id="assistant-note" class="mt-2 text-xs text-slate-500">{{ __('Only you see this conversation; it is kept for :days days and you can delete it at any time. Questions for figures and report drafts are recorded in the activity log without their text. Answers here are never written by an AI model.', ['days' => (int) config('assistant.retention_days.account')]) }}</p>
        </form>
    </div>
</x-layouts.customer>
