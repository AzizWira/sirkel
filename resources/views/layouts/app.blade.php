<!doctype html>
<html lang="id" data-user-theme="{{ auth()->user()->theme_preference ?? 'system' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="authenticated-user" content="{{ auth()->id() }}">
    <title>@yield('title', 'SIRKEL')</title>
    <link rel="icon" type="image/png" href="{{ asset('brand/sirkel-favicon.png') }}">
    <x-theme-bootstrap />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body>
    @php
        $role = auth()->user()->activeAccessRole();
        $accountPartnerProfile = auth()->user()->partnerProfile;
        $partnerProfile = $role === 'partner' ? $accountPartnerProfile : null;
        $caps = $partnerProfile?->capabilities?->where('status', 'approved')->pluck('capability')->all() ?? [];
        $unreadNotifications = auth()->user()->unreadNotifications()->count();
    @endphp
    <div class="app-shell">
        <aside class="sidebar" id="app-sidebar" aria-label="Menu aplikasi">
            <div class="sidebar-head">
                <a class="brand brand-logo-link" href="{{ route('home') }}"><x-brand-logo /></a>
                <button class="drawer-close" type="button" data-app-menu-close aria-label="Tutup menu">×</button>
            </div>


            @if($role === 'user')
                <div class="side-section">Warga</div>
                <a class="side-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}"
                    href="{{ route('user.dashboard') }}"><x-icon name="home" /> <span>Beranda</span></a>
                <a class="side-link {{ request()->routeIs('user.assets.*') || request()->routeIs('user.handovers.*') ? 'active' : '' }}"
                    href="{{ route('user.assets.index') }}"><x-icon name="box" /> <span>Barang</span></a>
                <a class="side-link {{ request()->routeIs('user.cart.*') || request()->routeIs('user.intake.*') ? 'active' : '' }}"
                    href="{{ route('user.cart.index') }}"><x-icon name="request" /> <span>Keranjang</span></a>
                <a class="side-link {{ request()->routeIs('user.bulk.*') ? 'active' : '' }}"
                    href="{{ route('user.bulk.create') }}"><x-icon name="sparkles" /> <span>Bulk AI <span
                            class="badge">PRO</span></span></a>
                <a class="side-link {{ request()->routeIs('user.activity') ? 'active' : '' }}"
                    href="{{ route('user.activity') }}"><x-icon name="activity" /> <span>Aktivitas</span></a>
                <a class="side-link {{ request()->routeIs('user.impact') ? 'active' : '' }}"
                    href="{{ route('user.impact') }}"><x-icon name="impact" /> <span>Dampak Saya</span></a>
                <a class="side-link {{ request()->routeIs('user.ai-quota.*') ? 'active' : '' }}"
                    href="{{ route('user.ai-quota.index') }}"><x-icon name="sparkles" /> <span>Kuota AI</span></a>
                @if(!$accountPartnerProfile || !$accountPartnerProfile->partner_access_granted_at || !$accountPartnerProfile->approval_acknowledged_at)
                    <a class="side-link {{ request()->routeIs('user.become-partner.*') ? 'active' : '' }}"
                        href="{{ route('user.become-partner.create') }}"><x-icon name="plus" /> <span>Jadi Mitra</span></a>
                @endif

            @elseif($role === 'partner')
                <div class="side-section">Mitra</div>
                <a class="side-link {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}"
                    href="{{ route('partner.dashboard') }}"><x-icon name="home" /> <span>Beranda Mitra</span></a>

                @if ($partnerProfile?->verification_status === 'approved')
                    <a class="side-link {{ request()->routeIs('partner.requests.*') ? 'active' : '' }}"
                        href="{{ route('partner.requests.index') }}"><x-icon name="request" /> <span>Permintaan Masuk</span></a>
                    <a class="side-link {{ request()->routeIs('partner.assets.*') || request()->routeIs('partner.transfers.*') ? 'active' : '' }}"
                        href="{{ route('partner.assets.index') }}"><x-icon name="box" /> <span>Barang Ditangani</span></a>
                @endif
                <a class="side-link {{ request()->routeIs('partner.onboarding.*') ? 'active' : '' }}"
                    href="{{ route('partner.onboarding.create') }}"><x-icon name="profile" /> <span>Profil Mitra</span></a>

            @else
                <div class="side-section">Admin</div>
                <a class="side-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                    href="{{ route('admin.dashboard') }}"><x-icon name="home" /> <span>Ringkasan</span></a>
                <a class="side-link {{ request()->routeIs('admin.partners.*') ? 'active' : '' }}"
                    href="{{ route('admin.partners.index') }}"><x-icon name="partners" /> <span>Mitra</span></a>
                <a class="side-link {{ request()->routeIs('admin.master.*') ? 'active' : '' }}"
                    href="{{ route('admin.master.index') }}"><x-icon name="database" /> <span>Kategori & Cek
                        Kondisi</span></a>
                <a class="side-link {{ request()->routeIs('admin.issues.*') ? 'active' : '' }}"
                    href="{{ route('admin.issues.index') }}"><x-icon name="flag" /> <span>Moderasi</span></a>
                <a class="side-link {{ request()->routeIs('admin.ai.*') ? 'active' : '' }}"
                    href="{{ route('admin.ai.index') }}"><x-icon name="sparkles" /> <span>AI & Biaya</span></a>
                <a class="side-link {{ request()->routeIs('admin.ai-quota.*') ? 'active' : '' }}"
                    href="{{ route('admin.ai-quota.index') }}"><x-icon name="request" /> <span>Top Up Kuota AI</span></a>
                <a class="side-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"
                    href="{{ route('admin.audit.index') }}"><x-icon name="audit" /> <span>Riwayat Sistem</span></a>
                <a class="side-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
                    href="{{ route('admin.settings.edit') }}"><x-icon name="settings" /> <span>Pengaturan</span></a>

            @endif

            <div class="sidebar-bottom">
                <a class="side-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}"
                    href="{{ route('notifications.index') }}"><x-icon
                        name="{{ $unreadNotifications ? 'bell-unread' : 'bell-read' }}" /> <span>Notifikasi
                        @if($unreadNotifications)
                            <span class="nav-count">{{ $unreadNotifications }}</span>
                        @endif
                    </span></a>
                <div class="theme-control" data-theme-control>
                    <div class="theme-control-title"><x-icon name="palette" /><span>Tampilan</span></div>
                    <div class="theme-options" role="group" aria-label="Pilih tema tampilan">
                        <button class="theme-option" type="button" data-theme-choice="light"
                            onclick="setSirkelTheme('light')" aria-pressed="false"><x-icon name="sun"
                                size="16" /><span>Terang</span></button>
                        <button class="theme-option" type="button" data-theme-choice="dark"
                            onclick="setSirkelTheme('dark')" aria-pressed="false"><x-icon name="moon"
                                size="16" /><span>Gelap</span></button>
                        <button class="theme-option" type="button" data-theme-choice="system"
                            onclick="setSirkelTheme('system')" aria-pressed="false"><x-icon name="monitor"
                                size="16" /><span>Sistem</span></button>
                    </div>
                    <small class="theme-hint" data-theme-description>Ikuti pengaturan perangkat.</small>
                </div>

                @if($role === 'user')
                    <a class="side-link" href="{{ route('user.profile.edit') }}"><x-icon name="profile" />
                        <span>Akun</span></a>
                @endif
                <form action="{{ route('logout') }}" method="post">@csrf<button class="side-link"
                        style="width:100%;border:0;background:none"><x-icon name="logout" />
                        <span>Keluar</span></button></form>
            </div>
        </aside>

        <button class="app-drawer-backdrop" type="button" data-app-menu-backdrop aria-label="Tutup menu"></button>

        <div class="app-main">
            <header class="app-topbar">
                <div class="topbar-left">
                    <button class="hamburger app-menu-toggle" type="button" data-app-menu-toggle
                        aria-controls="app-sidebar" aria-expanded="false" aria-label="Buka menu">
                        <span></span><span></span><span></span>
                    </button>
                    <strong class="topbar-title">@yield('topbar', 'SIRKEL')</strong>
                </div>
                <div class="right">
                    <a class="badge topbar-notification" href="{{ route('notifications.index') }}"
                        aria-label="Notifikasi"><x-icon name="{{ $unreadNotifications ? 'bell-unread' : 'bell-read' }}"
                            size="15" /><span class="topbar-notification-count">{{ $unreadNotifications }}</span><span
                            class="topbar-notification-label"> notifikasi</span></a>
                    <span class="badge topbar-user-name"><x-icon name="profile"
                            size="15" />{{ auth()->user()->name }}</span>
                </div>
            </header>
            <main class="app-content"><x-flash />@yield('content')</main>
        </div>
    </div>

    @if($role === 'user')
        <nav class="mobile-bottom">
            <a class="{{ request()->routeIs('user.dashboard') ? 'active' : '' }}" href="{{ route('user.dashboard') }}"><x-icon
                    name="home" />Beranda</a>
            <a class="{{ request()->routeIs('user.assets.*') || request()->routeIs('user.handovers.*') ? 'active' : '' }}"
                href="{{ route('user.assets.index') }}"><x-icon name="box" />Barang</a>
            <a class="{{ request()->routeIs('user.activity') ? 'active' : '' }}" href="{{ route('user.activity') }}"><x-icon
                    name="activity" />Aktivitas</a>
            <a class="{{ request()->routeIs('user.impact') ? 'active' : '' }}" href="{{ route('user.impact') }}"><x-icon
                    name="impact" />Dampak</a>
            <a class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}"
                href="{{ route('notifications.index') }}"><x-icon
                    name="{{ $unreadNotifications ? 'bell-unread' : 'bell-read' }}" />Notifikasi</a>
        </nav>
    @elseif($role === 'partner')
        <nav class="mobile-bottom"
            style="grid-template-columns:repeat({{ $partnerProfile?->verification_status === 'approved' ? 5 : 3 }},1fr)">
            <a class="{{ request()->routeIs('partner.dashboard') ? 'active' : '' }}"
                href="{{ route('partner.dashboard') }}"><x-icon name="home" />Beranda</a>
            @if($partnerProfile?->verification_status === 'approved')
                <a class="{{ request()->routeIs('partner.requests.*') ? 'active' : '' }}"
                    href="{{ route('partner.requests.index') }}"><x-icon name="request" />Permintaan</a>
                <a class="{{ request()->routeIs('partner.assets.*') || request()->routeIs('partner.transfers.*') ? 'active' : '' }}"
                    href="{{ route('partner.assets.index') }}"><x-icon name="box" />Ditangani</a>
            @endif
            <a class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}"
                href="{{ route('notifications.index') }}"><x-icon
                    name="{{ $unreadNotifications ? 'bell-unread' : 'bell-read' }}" />Notifikasi</a>
            <a class="{{ request()->routeIs('partner.onboarding.*') ? 'active' : '' }}"
                href="{{ route('partner.onboarding.create') }}"><x-icon name="profile" />Profil</a>
        </nav>
    @else
        <nav class="mobile-bottom" style="grid-template-columns:repeat(4,1fr)">
            <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><x-icon
                    name="home" />Ringkasan</a>
            <a class="{{ request()->routeIs('admin.partners.*') ? 'active' : '' }}"
                href="{{ route('admin.partners.index') }}"><x-icon name="partners" />Mitra</a>
            <a class="{{ request()->routeIs('admin.issues.*') ? 'active' : '' }}"
                href="{{ route('admin.issues.index') }}"><x-icon name="flag" />Moderasi</a>
            <a class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}"
                href="{{ route('notifications.index') }}"><x-icon
                    name="{{ $unreadNotifications ? 'bell-unread' : 'bell-read' }}" />Notifikasi</a>
        </nav>
    @endif

    @yield('modals')
    @stack('scripts')
</body>

</html>