<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#fcfbf9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1014" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="MacroDB">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <title>MacroDB</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app" class="mx-auto min-h-dvh max-w-lg pb-40">
        <main id="view" class="px-5 pt-[calc(1.5rem+env(safe-area-inset-top))]" aria-live="polite"></main>
    </div>
    <div id="cart-bar"></div>
    <nav id="tabs" class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 backdrop-blur pb-[env(safe-area-inset-bottom)]" aria-label="Fő navigáció"></nav>
</body>
</html>
