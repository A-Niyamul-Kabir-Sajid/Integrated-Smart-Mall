<x-layouts.customer :title="__('News and events')" :mall="$mall">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('malls.show', $mall->slug) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $mall->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('News and events') }}</h1>

        <section class="mt-6" aria-labelledby="events-heading">
            <h2 id="events-heading" class="text-lg font-semibold">{{ __('Events') }}</h2>
            @forelse ($events as $event)
                <article class="mt-3 rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="font-semibold">{{ $event->title }}</h3>
                    <p class="text-sm text-slate-600">
                        <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->timezone($mall->timezone)->format('D d M Y, H:i') }}</time>
                        @if ($event->ends_at) – <time datetime="{{ $event->ends_at->toIso8601String() }}">{{ $event->ends_at->timezone($mall->timezone)->format('H:i') }}</time>@endif
                        @if ($event->location_note) · {{ $event->location_note }}@endif
                    </p>
                    <p class="mt-2 whitespace-pre-line text-slate-700">{{ $event->body }}</p>
                </article>
            @empty
                <p class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No events planned right now.') }}</p>
            @endforelse
        </section>

        <section class="mt-8" aria-labelledby="notices-heading">
            <h2 id="notices-heading" class="text-lg font-semibold">{{ __('Notices') }}</h2>
            @forelse ($notices as $notice)
                <article class="mt-3 rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="font-semibold">{{ $notice->title }}</h3>
                    <p class="text-sm text-slate-600">{{ $notice->published_at->timezone($mall->timezone)->format('d M Y') }}</p>
                    <p class="mt-2 whitespace-pre-line text-slate-700">{{ $notice->body }}</p>
                </article>
            @empty
                <p class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No notices right now.') }}</p>
            @endforelse
        </section>
    </div>
</x-layouts.customer>
