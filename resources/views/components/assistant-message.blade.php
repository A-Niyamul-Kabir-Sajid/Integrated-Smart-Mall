@props(['message', 'latest' => false])

@php($states = ['superseded' => __('From an earlier version of this document.'), 'withdrawn' => __('This source has since been withdrawn.'), 'unavailable' => __('This source is no longer available.')])

<li @if ($latest) id="latest" @endif @class(['flex scroll-mt-24', 'justify-end' => ! $message['isAssistant']])>
    @if (! $message['isAssistant'])
        <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-brand-600 px-4 py-2.5 text-white">
            <p class="sr-only">{{ __('You asked') }}</p>
            <p class="whitespace-pre-line break-words">{{ $message['text'] }}</p>
        </div>
    @else
        <article class="max-w-[92%] space-y-3 rounded-2xl rounded-bl-sm border border-slate-200 bg-white px-4 py-3" aria-label="{{ __('Answer from the assistant') }}">
            <p class="whitespace-pre-line break-words text-slate-900">{{ $message['text'] }}</p>

            @if ($message['outcome'] === 'low_confidence')
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900" role="note">{{ __('This may not answer your question. Check the source or ask at the information desk.') }}</p>
            @endif

            @if ($message['offers'] !== [])
                <ul class="grid gap-2 sm:grid-cols-2" aria-label="{{ __('Offers from the catalogue') }}">
                    @foreach ($message['offers'] as $offer)
                        <li class="rounded-xl border border-slate-200 p-3 text-sm">
                            @if ($offer['gone'])
                                <p class="text-slate-500">{{ __('This offer is no longer on sale.') }}</p>
                            @else
                                <p class="font-medium"><a href="{{ $offer['productUrl'] }}" class="text-brand-700 hover:underline">{{ $offer['product'] }}</a>@if ($offer['option']) <span class="font-normal text-slate-600">· {{ $offer['option'] }}</span>@endif</p>
                                <p class="text-slate-700">{{ $offer['shop'] }}@if ($offer['location']) · {{ $offer['location'] }}@endif</p>
                                <p class="mt-1 flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{ $offer['currency'] }} {{ $offer['price'] }}</span>
                                    <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $offer['available'], 'bg-slate-100 text-slate-600' => ! $offer['available']])>{{ $offer['available'] ? __('Available') : __('Currently unavailable') }}</span>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">{{ $offer['reason'] }}</p>
                                <a href="{{ $offer['directionsUrl'] }}" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline"><x-icon name="pin" class="size-3.5" />{{ __('Directions to :place', ['place' => $offer['shop']]) }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($message['handoffs'] !== [])
                <ul class="flex flex-wrap gap-2" aria-label="{{ __('Directions') }}">
                    @foreach ($message['handoffs'] as $handoff)
                        <li>
                            <a href="{{ $handoff['url'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-600 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50">
                                <x-icon name="pin" class="size-4" />{{ __('Directions to :place', ['place' => $handoff['label']]) }}@if ($handoff['detail']) <span class="font-normal text-slate-600">· {{ $handoff['detail'] }}</span>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @foreach ($message['actions'] as $action)
                <form method="POST" action="{{ $action['url'] }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @foreach ($action['fields'] ?? [] as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">{{ $action['label'] }}</button>
                    @if (! empty($action['hint'])) <span class="text-xs text-slate-500">{{ $action['hint'] }}</span> @endif
                </form>
            @endforeach

            @if ($message['links'] !== [])
                <ul class="flex flex-wrap gap-3 text-sm">
                    @foreach ($message['links'] as $link)
                        <li><a href="{{ $link['url'] }}" class="font-medium text-brand-700 hover:underline">{{ __($link['label']) }}</a></li>
                    @endforeach
                </ul>
            @endif

            @if ($message['citations'] !== [] || $message['liveSources'] !== [])
                <details class="text-sm">
                    <summary class="cursor-pointer font-medium text-slate-700">{{ __('Sources') }} ({{ count($message['citations']) + count($message['liveSources']) }})</summary>
                    <ol class="mt-2 space-y-2">
                        @foreach ($message['citations'] as $citation)
                            <li class="rounded-lg bg-slate-50 px-3 py-2">
                                <p class="font-medium">[{{ $citation['number'] }}] {{ $citation['title'] ?? __('Source') }}@if ($citation['heading']) · {{ $citation['heading'] }}@endif</p>
                                @if ($citation['excerpt'])
                                    <p class="mt-0.5 whitespace-pre-line text-slate-700">{{ $citation['excerpt'] }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-slate-500">
                                    @if ($citation['state'] === 'current')
                                        {{ $citation['kind'] ? __($citation['kind']).' · ' : '' }}{{ __('as of :date', ['date' => $citation['captured']->format('d M Y H:i')]) }}
                                    @else
                                        {{ $states[$citation['state']] }}
                                    @endif
                                </p>
                            </li>
                        @endforeach
                        @foreach ($message['liveSources'] as $source)
                            <li class="rounded-lg bg-slate-50 px-3 py-2">
                                <p class="font-medium">[{{ $source['number'] }}] {{ __($source['title']) }}</p>
                                <p class="mt-0.5 text-slate-700">{{ $source['text'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ __('Read from the mall records at :time', ['time' => $message['time']->format('d M Y H:i')]) }}</p>
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endif

            @foreach ($message['notes'] as $note)
                <p class="text-xs text-slate-500">{{ $note }}</p>
            @endforeach

            <p class="text-xs text-slate-400">
                {{ $message['time']->format('H:i') }}
                @if ($message['driver'] === 'gemini')
                    · {{ __('Worded by an AI model from the sources') }}
                @elseif ($message['citations'] !== [])
                    · {{ __('Quoted from the sources') }}
                @endif
            </p>
        </article>
    @endif
</li>
