<x-layouts.customer :title="__('Announcements and events')" :mall="$mall">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('mall-admin.dashboard', $mall) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ __('Announcements and events') }}</h1>
            <a href="{{ route('mall-admin.announcements.create', $mall) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('Write an announcement') }}</a>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ __('Published announcements appear on the mall page and at') }} <a href="{{ route('announcements.index', $mall->slug) }}" class="font-medium text-brand-700 hover:underline">/malls/{{ $mall->slug }}/news</a>.</p>

        @if (session('status')) <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p> @endif
        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="{{ __('Status') }}">
            <x-nav-link :href="route('mall-admin.announcements.index', $mall)" :active="$status === null">{{ __('All') }}</x-nav-link>
            @foreach ($statuses as $option)
                <x-nav-link :href="route('mall-admin.announcements.index', [$mall, 'status' => $option->value])" :active="$status === $option->value">{{ $option->label() }}</x-nav-link>
            @endforeach
        </nav>

        @if ($announcements->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('Nothing here yet.') }}</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                @foreach ($announcements as $announcement)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <span class="font-medium">{{ $announcement->title }}</span>
                            <span class="text-sm text-slate-600">· {{ $announcement->kind->label() }} · {{ $announcement->status->label() }}@if ($announcement->starts_at) · {{ $announcement->starts_at->timezone($mall->timezone)->format('d M Y H:i') }}@endif</span>
                        </span>
                        <span class="flex flex-wrap items-center gap-3 text-sm">
                            @if ($announcement->status->value !== 'archived')
                                <a href="{{ route('mall-admin.announcements.edit', [$mall, $announcement]) }}" class="font-medium text-brand-700 hover:underline" aria-label="{{ __('Edit :title', ['title' => $announcement->title]) }}">{{ __('Edit') }}</a>
                            @endif
                            @foreach ($announcement->status->allowedTransitions() as $next)
                                <form method="POST" action="{{ route('mall-admin.announcements.'.['published' => 'publish', 'draft' => 'unpublish', 'archived' => 'archive'][$next->value], [$mall, $announcement]) }}">
                                    @csrf
                                    <button type="submit" class="font-medium text-slate-700 hover:underline" aria-label="{{ ['published' => __('Publish :title', ['title' => $announcement->title]), 'draft' => __('Unpublish :title', ['title' => $announcement->title]), 'archived' => __('Archive :title', ['title' => $announcement->title])][$next->value] }}">{{ ['published' => __('Publish'), 'draft' => __('Unpublish'), 'archived' => __('Archive')][$next->value] }}</button>
                                </form>
                            @endforeach
                        </span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $announcements->links() }}</div>
        @endif
    </div>
</x-layouts.customer>
