<!doctype html>
<html lang="id" data-user-theme="system">
<head>
    @php
        $metaTitle = html_entity_decode(trim($__env->yieldContent('title', 'SIRKEL | Pengelolaan E-Waste & Elektronik Sirkular Surabaya')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $metaDescription = html_entity_decode(trim($__env->yieldContent('meta_description', 'SIRKEL membantu warga Surabaya mengenali dan menangani elektronik tak terpakai dengan bantuan AI opsional, cek kondisi, pilihan mitra, dan pelacakan hasil penanganan.')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $metaRobots = trim($__env->yieldContent('robots', 'index,follow,max-image-preview:large'));
        $canonicalUrl = trim($__env->yieldContent('canonical', request()->fullUrlWithoutQuery(['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','fbclid'])));
        $ogImage = asset('brand/sirkel-wordmark-light.png');
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $metaRobots }}">
    <meta name="theme-color" content="#134a43">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">
    <meta name="application-name" content="SIRKEL">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SIRKEL">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="128x128" href="{{ asset('brand/sirkel-favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('brand/apple-touch-icon.png') }}">
    <x-theme-bootstrap/>

    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SIRKEL">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:alt" content="Logo SIRKEL">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    @vite(['resources/css/app.css','resources/js/app.js'])
    @stack('head')
</head>
<body>
<header class="public-header">
    <div class="container public-nav">
        <a class="brand brand-logo-link" href="{{ route('home') }}" aria-label="SIRKEL"><x-brand-logo/></a>

        <nav class="nav-links" aria-label="Navigasi utama">
            <a href="{{ route('home') }}">Beranda</a>
            <a href="{{ route('public.partners') }}">Mitra</a>
            <a href="{{ route('home') }}#bantuan-ai">Bantuan AI</a>
            <a href="{{ route('public.education') }}">Edukasi</a>
        </nav>

        <div class="nav-actions">
            <button class="btn pwa-install-button" type="button" hidden data-pwa-install>Instal</button>
            <button class="icon-btn" type="button" onclick="cycleSirkelTheme()" title="Ganti tema" aria-label="Ganti tema">◐</button>
            @auth
                <a class="btn btn-primary" href="{{ auth()->user()->isAdmin()?route('admin.dashboard'):(auth()->user()->isPartner()?route('partner.dashboard'):route('user.dashboard')) }}">Buka Aplikasi</a>
            @else
                <a class="btn" href="{{ route('login') }}">Masuk</a>
                <a class="btn btn-primary" href="{{ route('register') }}">Daftar</a>
            @endauth
        </div>

        <button class="hamburger public-menu-toggle" type="button" data-public-menu-toggle aria-controls="public-mobile-menu" aria-expanded="false" aria-label="Buka menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div id="public-mobile-menu" class="public-mobile-menu" aria-hidden="true">
        <nav class="public-mobile-links" aria-label="Navigasi seluler">
            <a href="{{ route('home') }}">Beranda</a>
            <a href="{{ route('public.partners') }}">Mitra</a>
            <a href="{{ route('home') }}#bantuan-ai">Bantuan AI</a>
            <a href="{{ route('public.education') }}">Edukasi</a>
        </nav>
        <div class="public-mobile-actions">
            <button class="btn pwa-install-button" type="button" hidden data-pwa-install>Instal SIRKEL</button>
            <button class="btn" type="button" onclick="cycleSirkelTheme()">◐ Ganti Tema</button>
            @auth
                <a class="btn btn-primary" href="{{ auth()->user()->isAdmin()?route('admin.dashboard'):(auth()->user()->isPartner()?route('partner.dashboard'):route('user.dashboard')) }}">Buka Aplikasi</a>
            @else
                <a class="btn" href="{{ route('login') }}">Masuk</a>
                <a class="btn btn-primary" href="{{ route('register') }}">Daftar</a>
            @endauth
        </div>
    </div>
</header>
<div class="public-menu-backdrop" data-public-menu-backdrop></div>

<main>@yield('content')</main>
<footer class="footer">
    <div class="container split">
        <div><strong>SIRKEL</strong> · Elektronik sirkular Surabaya</div>
        <div>Mulai dari Gunung Anyar</div>
    </div>
</footer>
</body>
</html>
