@props(['mall'])

@inject('announcementService', 'App\Modules\MallOwnerAdmin\Services\AnnouncementService')
@php($news = $announcementService->publicFor($mall, 3))

<section class="mx-auto max-w-6xl px-4 pt-10" aria-labelledby="news-heading" data-integration="announcements">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="news-heading" class="text-lg font-semibold">{{ __('News and events') }}</h2>
        <a href="{{ route('announcements.index', $mall->slug) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('All news and events') }}</a>
    </div>
    @if ($news['events']->isEmpty() && $news['notices']->isEmpty())
        <p class="mt-3 text-sm text-slate-600">{{ __('Nothing to announce right now.') }}</p>
    @else
        <ul class="mt-3 grid gap-3 md:grid-cols-3">
            @foreach ($news['events'] as $event)
                <li class="rounded-xl border border-brand-200 bg-brand-50 p-4">
                    <p class="text-xs font-medium uppercase text-brand-700">{{ __('Event') }} · <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->timezone($mall->timezone)->format('D d M, H:i') }}</time></p>
                    <p class="mt-1 font-medium">{{ $event->title }}</p>
                    @if ($event->location_note)<p class="text-sm text-slate-600">{{ $event->location_note }}</p>@endif
                </li>
            @endforeach
            @foreach ($news['notices'] as $notice)
                <li class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium uppercase text-slate-500">{{ __('Notice') }} · {{ $notice->published_at->timezone($mall->timezone)->format('d M') }}</p>
                    <p class="mt-1 font-medium">{{ $notice->title }}</p>
                    <p class="text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($notice->body, 110) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
