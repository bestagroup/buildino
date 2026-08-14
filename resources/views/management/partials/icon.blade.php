@php
    $size = $size ?? 20;
    $class = $class ?? '';
@endphp

<svg
    class="ui-icon {{ $class }}"
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>
    @switch($name)
        @case('home')
            <path d="M3 11.5 12 4l9 7.5"/>
            <path d="M5.5 10.5V20h13v-9.5"/>
            <path d="M9.5 20v-6h5v6"/>
            @break

        @case('building')
            <path d="M4 21V5l8-2v18"/>
            <path d="M12 8h8v13"/>
            <path d="M7 8h2M7 12h2M7 16h2M15 11h2M15 15h2"/>
            @break

        @case('users')
            <circle cx="9" cy="8" r="3"/>
            <path d="M3.5 20c.3-4 2.3-6 5.5-6s5.2 2 5.5 6"/>
            <path d="M16 5.5a3 3 0 0 1 0 5.8M16 14c2.8.4 4.2 2.2 4.5 5"/>
            @break

        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"/>
            <path d="M7 3v4M17 3v4M3 10h18"/>
            <path d="M8 14h3M14 14h2M8 17h2"/>
            @break

        @case('invoice')
            <path d="M6 3h9l3 3v15H6z"/>
            <path d="M15 3v4h4M9 11h6M9 15h6M9 18h4"/>
            @break

        @case('wallet')
            <path d="M4 7h14a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V7a3 3 0 0 1 3-3h12"/>
            <path d="M20 11h-5a2 2 0 0 0 0 4h5"/>
            @break

        @case('tools')
            <path d="M14.5 6.5a4 4 0 0 0-5-5l2.2 2.2-2 2-2.2-2.2a4 4 0 0 0 5 5L20 16l-4 4-7.5-7.5"/>
            <path d="m4 20 5-5"/>
            @break

        @case('support')
            <path d="M4 13a8 8 0 0 1 16 0"/>
            <path d="M4 13v5h3v-6H4M20 13v5h-3v-6h3"/>
            <path d="M17 19c-1 1.3-2.7 2-5 2"/>
            @break

        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
            <path d="M10 21h4"/>
            @break

        @case('chart')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
            @break

        @case('shield')
            <path d="M12 3 4.5 6v5.5c0 4.8 3 7.8 7.5 9.5 4.5-1.7 7.5-4.7 7.5-9.5V6z"/>
            <path d="m9 12 2 2 4-5"/>
            @break

        @case('api')
            <path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"/>
            @break

        @case('health')
            <path d="M3 12h4l2-5 4 10 2-5h6"/>
            @break

        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16"/>
            @break

        @case('logout')
            <path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9"/>
            @break

        @case('sun')
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            @break

        @case('moon')
            <path d="M20 15.5A8 8 0 0 1 8.5 4 8.5 8.5 0 1 0 20 15.5z"/>
            @break

        @case('filter')
            <path d="M4 5h16M7 12h10M10 19h4"/>
            @break

        @case('money')
            <circle cx="12" cy="12" r="9"/>
            <path d="M15 8.5c-.7-.8-1.6-1.2-3-1.2-1.7 0-3 .8-3 2s1 1.8 3 2.2 3 1 3 2.4-1.3 2.5-3 2.5c-1.5 0-2.7-.5-3.5-1.4M12 5.5v13"/>
            @break

        @default
            <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
