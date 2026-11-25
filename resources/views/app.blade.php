<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'LWE Learning Portal') }}</title>

        {{-- Installable app (PWA) --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#0B2447">
        <link rel="icon" type="image/png" href="/icons/favicon-48.png">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="LWE Portal">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="bg-lwe-bg font-sans text-slate-800 antialiased">
        @inertia
        <script>
            // Register the service worker (needs https, or localhost / 127.0.0.1 on your PC).
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
            }
            // Keep the browser's "Install app" offer so the portal can show its own button.
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); window.__lweInstall = e; window.dispatchEvent(new Event('lwe-installable')); });
        </script>
    </body>
</html>
