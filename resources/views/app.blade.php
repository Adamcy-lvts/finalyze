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

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,500,600,700|outfit:400,500,600,700,800" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
        <script src="https://js.paystack.co/v1/inline.js"></script>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
