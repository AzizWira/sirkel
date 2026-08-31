@props(['name', 'size' => 18])
<svg {{ $attributes->merge(['class' => 'ui-icon', 'width' => $size, 'height' => $size, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'xmlns' => 'http://www.w3.org/2000/svg', 'aria-hidden' => 'true']) }}>

@switch($name)

@case('home')
<path d="M3 10.75 12 3l9 7.75V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10.75Z"/>@break

@case('box')
<path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="M4 7v10l8 4 8-4V7M12 11v10"/>@break

@case('activity')
<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M8 12h3l2-4 3 8 2-4h3"/>@break

@case('impact')
<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 3v2M21 12h-2M12 21v-2M3 12h2"/>@break

@case('plus')
<path d="M12 5v14M5 12h14"/>@break

@case('request')
<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>@break

@case('location')
<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>@break

@case('repair')
<path d="m14.7 6.3 3-3a5 5 0 0 1-6.4 6.4L5 16l3 3 6.3-6.3a5 5 0 0 1 6.4-6.4l-3 3-3-3Z"/><path d="m4 17 3 3"/>@break

@case('reuse')
<path d="M7 7h10l-2.5-2.5M17 17H7l2.5 2.5"/><path d="M17 7a5 5 0 0 1 0 10M7 17A5 5 0 0 1 7 7"/>@break

@case('recycle')
<path d="m9 4 3-2 3 2M12 2v6"/><path d="m20 13 1 3-3 2M21 16l-5-3"/><path d="m4 13-1 3 3 2M3 16l5-3"/><path d="M8 8h8l4 7-4 5H8l-4-5 4-7Z"/>@break

@case('profile')
<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>@break

@case('swap')
<path d="M7 7h12l-3-3M17 17H5l3 3"/><path d="m19 7-3 3M5 17l3-3"/>@break

@case('partners')
<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20a6 6 0 0 1 12 0M14 15a5 5 0 0 1 7 4.5"/>@break

@case('database')
<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>@break

@case('flag')
<path d="M5 22V3M5 4h11l-2 4 2 4H5"/>@break

@case('sparkles')
<path d="m12 3 1.1 3.4L16.5 7.5l-3.4 1.1L12 12l-1.1-3.4-3.4-1.1 3.4-1.1L12 3ZM18.5 13l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2ZM6 14l.8 2.2L9 17l-2.2.8L6 20l-.8-2.2L3 17l2.2-.8L6 14Z"/>@break

@case('image')
<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m5 18 5-5 3 3 2-2 4 4"/>@break

@case('camera')
<path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13" r="3.5"/>@break

@case('clock')
<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break

@case('check')
<path d="m5 12 4 4L19 6"/>@break

@case('audit')
<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h5M8 16h4"/><circle cx="17" cy="16" r="2"/><path d="m18.5 17.5 2 2"/>@break

@case('settings')
<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1L7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>@break

@case('bell')
<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>@break

@case('bell-unread')
<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/><path d="M4.5 4.5 3 3M19.5 4.5 21 3"/>@break

@case('bell-read')
<path d="M18 9a6 6 0 0 0-12 0c0 5.5-2.3 6.6-3 8h18c-.7-1.4-3-2.5-3-8"/><path d="M9.5 20h5"/><path d="m8.8 12.3 2 2 4.5-4.5"/>@break

@case('logout')
<path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"/><path d="m14 8 4 4-4 4M18 12H8"/>@break

@case('sun')
<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>@break

@case('moon')
<path d="M20.5 15.5A8.5 8.5 0 0 1 8.5 3.5a8.5 8.5 0 1 0 12 12Z"/>@break

@case('monitor')
<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>@break

@case('palette')
<path d="M12 3a9 9 0 1 0 0 18h1.2a2 2 0 0 0 1.6-3.2 2 2 0 0 1 1.6-3.2H18A3 3 0 0 0 21 11.5 8.5 8.5 0 0 0 12 3Z"/><circle cx="7.5" cy="10" r="1"/><circle cx="10" cy="6.5" r="1"/><circle cx="15" cy="7" r="1"/>@break
        @default<circle cx="12" cy="12" r="8"/>@break

@endswitch
</svg>
