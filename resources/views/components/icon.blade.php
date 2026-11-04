@props(['name'])

<svg {{ $attributes->merge(['class' => 'size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('home')
            <path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" />
            @break
        @case('shops')
            <path d="M4 9h16l-1.5-5h-13zM5 9v11h14V9M9 20v-6h6v6" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
            @break
        @case('map')
            <path d="m9 4-6 2.5v13L9 17l6 2.5 6-2.5v-13L15 6.5zM9 4v13M15 6.5v13" />
            @break
        @case('pin')
            <path d="M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z" /><circle cx="12" cy="9" r="2.5" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('phone')
            <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" />
            @break
        @case('facility')
            <circle cx="12" cy="12" r="9" /><path d="M12 8h.01M11 12h1v4h1" />
            @break
        @case('chart')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
            @break
        @case('box')
            <path d="m21 8-9-5-9 5 9 5zM3 8v8l9 5 9-5V8M12 13v8" />
            @break
        @default
            <circle cx="12" cy="12" r="9" />
    @endswitch
</svg>
