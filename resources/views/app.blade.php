<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Phase-26 PWA-MANIFEST-1: installability prerequisite. The
             manifest declares icons + display + start_url so Chromium
             and Edge surface the Add-to-Home-Screen prompt. iOS Safari
             ignores it (it reads apple-touch-icon + apple-mobile-web-app-*
             meta tags instead — see PWA-MANIFEST-3 below). --}}
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#1f2937">

        {{-- Phase-26 PWA-MANIFEST-3: iOS PWA support. iOS Safari does
             NOT read the manifest icons[]; it requires apple-touch-icon
             at the document root. Without it, Add-to-Home-Screen
             renders a screenshot of the page. --}}
        <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="PropManager">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes(nonce: Vite::cspNonce())
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
