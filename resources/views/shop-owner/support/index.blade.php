<x-layouts.customer :title="__('Customer support').' · '.$shop->name">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Customer support') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Reviews are answered in the review inbox.') }} <a href="{{ route('workspace.reviews.index', $shop) }}" class="font-medium text-brand-700 hover:underline">{{ __('Open the review inbox') }}</a></p>

        <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="{{ __('Conversation status') }}">
            <x-nav-link :href="route('workspace.support.index', $shop)" :active="$status === null">{{ __('All') }}</x-nav-link>
            @foreach ($statuses as $option)
                <x-nav-link :href="route('workspace.support.index', [$shop, 'status' => $option->value])" :active="$status === $option->value">{{ $option->label() }}</x-nav-link>
            @endforeach
        </nav>

        @if ($threads->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No conversations here yet.') }}</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($threads as $thread)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('workspace.support.show', [$shop, $thread]) }}" class="font-medium text-brand-700 hover:underline">{{ $thread->subject }}</a>
                            <span class="text-sm text-slate-600">· {{ $thread->customer->display_name }} · {{ $thread->kind->label() }} · {{ $thread->last_message_at->timezone('Asia/Dhaka')->format('d M H:i') }}</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ $thread->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $threads->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
