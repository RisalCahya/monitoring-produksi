@props(['name', 'class' => 'icon'])

<svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('factory')
            <path d="M3 21V8l6 3V8l6 3V4h6v17H3Z" /><path d="M18 8h.01M18 12h.01M18 16h.01M7 15h.01M11 15h.01M7 18h.01M11 18h.01" />
            @break
        @case('dashboard')
            <rect x="3" y="3" width="8" height="8" rx="1.5" /><rect x="13" y="3" width="8" height="5" rx="1.5" /><rect x="13" y="10" width="8" height="11" rx="1.5" /><rect x="3" y="13" width="8" height="8" rx="1.5" />
            @break
        @case('machine')
            <rect x="4" y="4" width="16" height="16" rx="2" /><path d="M9 8v8M15 8v8M4 9H2M4 15H2M22 9h-2M22 15h-2M9 4V2M15 4V2M9 22v-2M15 22v-2" />
            @break
        @case('history')
            <path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5M12 7v5l3 2" />
            @break
        @case('settings')
            <path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z" /><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.7-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.5-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.5.9l1.7-.7 1.4 2.4-1.4 1.1a7 7 0 0 1 0 1.8Z" transform="translate(-1 -1)" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" />
            @break
        @case('activity')
            <path d="M3 12h4l3-8 4 16 3-8h4" />
            @break
        @case('target')
            <circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1" />
            @break
        @case('trend')
            <path d="m3 17 6-6 4 4 8-8" /><path d="M15 7h6v6" />
            @break
        @case('package')
            <path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 8 9 5 9-5M3 8v8l9 5 9-5V8M12 13v8" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('radio')
            <path d="M5 8a10 10 0 0 1 14 0M8 11a6 6 0 0 1 8 0M11 14a2 2 0 0 1 2 0" /><circle cx="12" cy="18" r="1" />
            @break
        @case('arrow-up-right')
            <path d="M7 17 17 7M7 7h10v10" />
            @break
        @case('check')
            <path d="m5 12 4 4L19 6" />
            @break
    @endswitch
</svg>
