@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @if ($hint) aria-describedby="{{ $name }}-hint" @endif
        @error($name) aria-invalid="true" @enderror
        {{ $attributes->class('mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500 disabled:bg-slate-50') }}>
    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
    @enderror
</div>
