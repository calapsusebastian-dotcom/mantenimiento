@props(['name'])

<svg {{ $attributes->merge(['class' => 'w-5 h-5', 'fill' => 'none', 'viewBox' => '0 0 24 24', 'stroke' => 'currentColor', 'stroke-width' => '1.75', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
    @switch($name)
        @case('home')
            <path d="M3 10.5 12 3l9 7.5" />
            <path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5" />
            @break
        @case('box')
            <path d="M21 8 12 3 3 8l9 5 9-5Z" />
            <path d="M3 8v8l9 5 9-5V8" />
            <path d="M12 13v8" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 10h18" />
            @break
        @case('clipboard')
            <rect x="6" y="4" width="12" height="17" rx="2" />
            <rect x="9" y="2" width="6" height="4" rx="1" />
            <path d="M9 11h6M9 15h6" />
            @break
        @case('alert')
            <path d="M12 3 22 20H2L12 3Z" />
            <path d="M12 9v5" />
            <circle cx="12" cy="17" r="0.75" fill="currentColor" stroke="none" />
            @break
        @case('logout')
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="m16 17 5-5-5-5" />
            <path d="M21 12H9" />
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
            @break
        @case('x-mark')
            <path d="M6 6l12 12M6 18 18 6" />
            @break
        @case('chart')
            <path d="M4 20h16M7 20V12M12 20V8M17 20V4" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="m8 12 3 3 5-6" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('arrow-left')
            <path d="M19 12H5M12 19l-7-7 7-7" />
            @break
        @case('wrench')
            <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 0 5.4-5.4l-2.8 2.8-2-2 2.8-2.8Z" />
            @break
        @case('users')
            <circle cx="9" cy="7" r="3" />
            <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" />
            <circle cx="17" cy="8" r="2.5" />
            <path d="M15 20c.3-2.7 2-5 4.5-5" />
            @break
        @case('logbook')
            <path d="M5 4h11a2 2 0 0 1 2 2v13l-3-2-3 2-3-2-3 2V6a2 2 0 0 1 2-2Z" />
            <path d="M8 9h6M8 13h4" />
            @break
    @endswitch
</svg>
