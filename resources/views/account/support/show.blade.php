<x-layouts.customer :title="$thread->subject">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('support.index') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your messages') }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $thread->subject }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $thread->status->label() }}</span>
        </div>
        <p class="text-slate-600">{{ $thread->shop->name }} · {{ $thread->kind->label() }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <ol class="mt-6 space-y-3" aria-label="{{ __('Messages') }}">
            @foreach ($thread->messages as $message)
                <li @class(['rounded-xl border p-4', 'border-brand-200 bg-brand-50' => $message->author_side === 'customer', 'border-slate-200 bg-white' => $message->author_side === 'shop'])>
                    <p class="text-xs text-slate-500">{{ $message->author_side === 'customer' ? __('You') : $message->author->display_name.' · '.$thread->shop->name }} · {{ $message->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $message->body }}</p>
                </li>
            @endforeach
        </ol>

        @if ($thread->status->value !== 'closed')
            <form method="POST" action="{{ route('support.reply', $thread) }}" class="mt-6 space-y-3">
                @csrf
                <div>
                    <label for="body" class="block text-sm font-medium">{{ __('Your reply') }}</label>
                    <textarea id="body" name="body" rows="3" required maxlength="2000" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('body') }}</textarea>
                    @error('body') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Send reply') }}</button>
            </form>
            <form method="POST" action="{{ route('support.close', $thread) }}" class="mt-3">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Close this conversation') }}</button>
            </form>
        @else
            <p class="mt-6 text-sm text-slate-600">{{ __('This conversation is closed.') }}</p>
        @endif
    </div>
</x-layouts.customer>
