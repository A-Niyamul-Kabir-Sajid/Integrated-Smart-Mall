<x-layouts.customer :title="__('Your messages to shops')">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('account.show') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ __('Your account') }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Your messages to shops') }}</h1>

        @if ($threads->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('You have not written to a shop yet. Open a shop page and choose Contact the shop.') }}</p>
        @else
            <ul class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($threads as $thread)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('support.show', $thread) }}" class="font-medium text-brand-700 hover:underline">{{ $thread->subject }}</a>
                            <span class="text-sm text-slate-600">· {{ $thread->shop->name }} · {{ $thread->kind->label() }} · {{ $thread->last_message_at->timezone('Asia/Dhaka')->format('d M H:i') }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $thread->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $threads->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
