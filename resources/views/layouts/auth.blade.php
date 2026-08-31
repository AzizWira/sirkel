<!doctype html>
<html lang="id" data-user-theme="system">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'SIRKEL')</title>
    <link rel="icon" type="image/png" href="{{ asset('brand/sirkel-favicon.png') }}">
    <x-theme-bootstrap />@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <main class="auth-page">
        <section class="auth-box"><a class="brand brand-logo-link"
                href="{{ route('home') }}"><x-brand-logo /></a><x-flash />@yield('content')</section>
    </main>@yield('modals')@stack('scripts')
</body>

</html>