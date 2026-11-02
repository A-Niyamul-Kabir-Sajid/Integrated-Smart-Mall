@props(['shop', 'mall'])

<li class="relative flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <h3 class="font-semibold">{{ $shop->name }}</h3>
        <x-open-badge :open="$shop->is_open_now" :closed-permanently="$shop->status->value === 'closed'" class="shrink-0" />
    </div>
    <p class="line-clamp-2 text-sm text-slate-600">{{ $shop->description }}</p>
    <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
        <span class="rounded bg-slate-100 px-1.5 py-0.5">{{ __($shop->category->name) }}</span>
        @foreach ($shop->units->where('status', '!=', 'archived') as $unit)
            <span class="inline-flex items-center gap-1"><x-icon name="pin" class="size-3.5" />{{ __($unit->floor->name) }} · {{ $unit->unit_code }}</span>
        @endforeach
    </div>
</li>
