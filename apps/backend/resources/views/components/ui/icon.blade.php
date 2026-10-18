@props(['name'])
<svg {{ $attributes->class(['inline-block shrink-0']) }} xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('layers')<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>@break
        @case('dashboard')<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>@break
        @case('users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3.13a4 4 0 0 1 0 7.75M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/>@break
        @case('bag')<path d="M3 7h18l-1.5 14h-15L3 7ZM8 7V5a4 4 0 0 1 8 0v2"/>@break
        @case('wallet')<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 9h18M16 14h2"/>@break
        @case('route')<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h7a4 4 0 0 0 0-8H9a4 4 0 0 1 0-8h7"/>@break
        @case('receipt')<path d="M4 2h16v20l-4-2-4 2-4-2-4 2V2ZM8 7h8M8 11h8M8 15h5"/>@break
        @case('user-cog')<circle cx="10" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 8-5.7M18 14v2m0 4v2m-4-4h2m4 0h2"/>@break
        @case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6M18 14v6m-3-3h6"/>@break
        @case('chart')<path d="M3 3v18h18M7 17l4-5 3 2 5-8"/>@break
        @case('settings')<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2"/><circle cx="16" cy="17" r="2"/>@break
        @case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
        @case('activity')<path d="M3 12h4l2-7 4 14 2-7h6"/>@break
        @case('theme')<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18V3Z"/>@break
        @case('chevrons')<path d="m7 9 5-5 5 5m-10 6 5 5 5-5"/>@break
        @case('logout')<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>@break
        @case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>@break
        @case('pin')<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/>@break
        @case('hand')<path d="M3 18h6l3 2h4l5-5a2 2 0 0 0-3-2l-3 2h-4M3 14h6l4 2M4 11V5h16v6"/>@break
        @case('plus')<path d="M12 5v14M5 12h14"/>@break
    @endswitch
</svg>
