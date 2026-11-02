@props(['title', 'subtitle' => null])

<div class="mx-auto flex max-w-md flex-col px-4 py-10">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-600">{{ $subtitle }}</p>
        @endif
        <div class="mt-6">
            {{ $slot }}
        </div>
    </div>
    @isset($footer)
        <p class="mt-4 text-center text-sm text-slate-600">{{ $footer }}</p>
    @endisset
</div>
