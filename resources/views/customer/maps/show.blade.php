<x-layouts.customer :title="__('Map')" :mall="$mall">
    <div class="mx-auto max-w-6xl space-y-4 px-4 py-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">{{ __('Map') }}</h1>
                <p class="mt-1 text-slate-600">
                    @if ($startLabel)
                        {{ __('You are at: :place', ['place' => $startLabel]) }}
                    @else
                        {{ __('Scan the QR code nearest to you to start from where you are, or choose your start below.') }}
                    @endif
                </p>
            </div>
            <p id="map-route-summary" class="text-sm font-medium text-brand-700" aria-live="polite"></p>
        </div>

        @if ($notice)
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900" role="status">{{ $notice }}</p>
        @endif

        @if ($embedUrl)
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <iframe id="mall-map" src="{{ $embedUrl }}" title="{{ __('Indoor map of :mall', ['mall' => $mall->name]) }}" class="block w-full border-0" style="height:72vh;min-height:520px" allow="fullscreen" referrerpolicy="no-referrer"></iframe>
            </div>
            <p class="text-sm text-slate-500">{{ __('Step-free routes use lifts and ramps only. Routes change at once when a walkway or lift is closed.') }}</p>
            <script>
                (() => {
                    const origin = @json($mapOrigin);
                    const summary = document.getElementById('map-route-summary');
                    const words = @json(['min' => __('min'), 'm' => __('m')]);
                    window.addEventListener('message', (event) => {
                        if (event.origin !== origin || !event.data || event.data.source !== 'ism-map') return;
                        if (event.data.type === 'ism-map:route') {
                            summary.textContent = `${event.data.total.minutes} ${words.min} · ${Math.round(event.data.total.length_m)} ${words.m}`;
                        } else if (event.data.type === 'ism-map:error') {
                            summary.textContent = '';
                        }
                    });
                })();
            </script>
        @endif
    </div>
</x-layouts.customer>
