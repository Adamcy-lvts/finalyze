<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
         <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Critical: Apply theme BEFORE any content renders to prevent flash --}}
        <script>
            (function() {
                // Priority: localStorage (client preference) > cookie (SSR) > system
                const stored = localStorage.getItem('appearance');
                const serverAppearance = '{{ $appearance ?? "system" }}';
                const appearance = stored || serverAppearance;
                
                let shouldBeDark = false;
                
                if (appearance === 'dark') {
                    shouldBeDark = true;
                } else if (appearance === 'system' || !appearance) {
                    shouldBeDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                // 'light' = false, already set
                
                // Apply or remove dark class synchronously
                if (shouldBeDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
                
                // Sync localStorage if it differs from what we applied
                if (!stored && serverAppearance) {
                    localStorage.setItem('appearance', serverAppearance);
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- PWA Meta Tags --}}
        <meta name="theme-color" content="#0a0a0f" media="(prefers-color-scheme: dark)">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Finalyze">
        <meta name="application-name" content="Finalyze">

        {{-- PWA Manifest --}}
        <link rel="manifest" href="/build/manifest.webmanifest">

        {{-- Favicons and Icons --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="mask-icon" href="/favicon.svg" color="#ffffff">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,500,600,700|outfit:400,500,600,700,800" rel="stylesheet" />

        @if (config('services.google_analytics.measurement_id'))
            <!-- Google tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.measurement_id') }}"></script>
            <script>
                window.GA_MEASUREMENT_ID = "{{ config('services.google_analytics.measurement_id') }}";
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                window.gtag = window.gtag || gtag;
                gtag('js', new Date());
                gtag('config', window.GA_MEASUREMENT_ID, { send_page_view: false });
            </script>
        @endif

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
        <script src="https://js.paystack.co/v1/inline.js"></script>
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- PWA Service Worker Registration & Install Prompt Capture --}}
        <script>
            // Capture beforeinstallprompt early before Vue mounts
            window.deferredPWAPrompt = null;
            window.addEventListener('beforeinstallprompt', function(e) {
                e.preventDefault();
                window.deferredPWAPrompt = e;
                console.log('PWA: beforeinstallprompt captured');
                // Dispatch custom event for Vue to pick up
                window.dispatchEvent(new CustomEvent('pwa-install-available'));
            });

            // Register service worker
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('/build/sw.js', { scope: '/' })
                        .then(function(registration) {
                            console.log('SW registered: ', registration);
                        })
                        .catch(function(error) {
                            console.log('SW registration failed: ', error);
                        });
                });
            }
        </script>
    </body>
</html>
