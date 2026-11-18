<x-layouts.customer :title="__('Assistant')" :mall="$mall">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">{{ __('Ask the assistant') }}</h1>
                <p class="mt-1 text-slate-600">{{ __('Shops, products and prices, facilities, opening hours and mall information, in English or Bangla.') }}</p>
            </div>
            @if ($hasConversation)
                <form method="POST" action="{{ route('assistant.destroy', ['publicMall' => $mall->slug]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ __('Delete this conversation') }}</button>
                </form>
            @endif
        </div>

        @if ($messages === [])
            <section class="rounded-xl border border-dashed border-slate-300 bg-white p-6" aria-labelledby="examples-heading">
                <h2 id="examples-heading" class="font-semibold">{{ __('Try asking') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($suggestions as $suggestion)
                        <li>
                            <form method="POST" action="{{ route('assistant.messages.store', ['publicMall' => $mall->slug]) }}">
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

        <form method="POST" action="{{ route('assistant.messages.store', ['publicMall' => $mall->slug]) }}" class="rounded-xl border border-slate-200 bg-white p-4">
            @csrf
            <label for="assistant-message" class="block text-sm font-medium">{{ __('Your question') }}</label>
            <div class="mt-1 flex gap-2">
                <input id="assistant-message" name="message" type="text" required maxlength="{{ (int) config('assistant.max_message_length') }}" value="{{ old('message') }}" autocomplete="off" @if ($messages !== []) autofocus @endif
                    aria-describedby="assistant-privacy" @error('message') aria-invalid="true" @enderror
                    placeholder="{{ __('e.g. Where can I buy a phone charger under 1500 taka?') }}"
                    class="min-w-0 flex-1 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700">{{ __('Ask') }}</button>
            </div>
            @error('message') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            <p id="assistant-privacy" class="mt-2 text-xs text-slate-500">
                {{ __('The assistant answers only from this mall\'s own information and says so when it does not know. Do not type personal details: email addresses and phone numbers are removed from what you write.') }}
                @if ($keptUntil)
                    {{ __('This conversation is kept until :date and you can delete it at any time.', ['date' => $keptUntil->format('d M Y H:i')]) }}
                @else
                    {{ auth()->check() ? __('A conversation is kept for :days days and you can delete it at any time.', ['days' => (int) config('assistant.retention_days.account')]) : __('A conversation is kept for :days day and you can delete it at any time.', ['days' => (int) config('assistant.retention_days.guest')]) }}
                @endif
                @if ($usesModel)
                    {{ __('Answers may be worded by an AI model (Google Gemini), which receives your question and the public sources shown with the answer.') }}
                @endif
            </p>
        </form>
    </div>
</x-layouts.customer>
