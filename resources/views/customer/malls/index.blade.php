<x-layouts.customer :title="__('Choose a mall')">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-semibold">{{ __('Choose a mall') }}</h1>
        @if ($malls->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No mall is published yet.') }}</p>
        @else
            @php($usualMallId = $usualMallId ?? null)
            <ul class="mt-6 space-y-3">
                {{-- The visitor's usual mall (a saved preference) comes first. --}}
                @foreach ($malls->sortBy(fn ($mall) => $mall->mall_id === $usualMallId ? 0 : 1, SORT_NUMERIC) as $mall)
                    <li>
                        <a href="{{ route('malls.show', ['publicMall' => $mall->slug]) }}" @class(['block rounded-xl border bg-white p-5 hover:border-brand-500', 'border-brand-500' => $mall->mall_id === $usualMallId, 'border-slate-200' => $mall->mall_id !== $usualMallId])>
                            <span class="font-semibold">{{ $mall->name }}</span>
                            @if ($mall->mall_id === $usualMallId)
                                <span class="ml-2 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">{{ __('Your usual mall') }}</span>
                            @endif
                            <span class="block text-sm text-slate-600">{{ $mall->address }}, {{ $mall->city }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.customer>
