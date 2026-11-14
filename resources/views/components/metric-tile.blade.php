@props(['label', 'value', 'hint' => null])

<div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
    <dt class="text-xs uppercase text-slate-500">{{ $label }}</dt>
    <dd class="mt-1 text-2xl font-semibold">{{ $value }}</dd>
    @if ($hint) <dd class="text-xs text-slate-500">{{ $hint }}</dd> @endif
</div>
