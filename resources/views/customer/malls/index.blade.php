<x-layouts.customer :title="__('Choose a mall')">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-semibold">{{ __('Choose a mall') }}</h1>
        @if ($malls->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">{{ __('No mall is published yet.') }}</p>
        @else
            <ul class="mt-6 space-y-3">
                @foreach ($malls as $mall)
                    <li>
                        <a href="{{ route('malls.show', ['publicMall' => $mall->slug]) }}" class="block rounded-xl border border-slate-200 bg-white p-5 hover:border-brand-500">
                            <span class="font-semibold">{{ $mall->name }}</span>
                            <span class="block text-sm text-slate-600">{{ $mall->address }}, {{ $mall->city }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.customer>
