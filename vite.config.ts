import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.ts',
                'resources/js/pages/projects/ChapterEditor.vue',
            ],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            outDir: 'public',
            filename: 'sw.js',
            manifestFilename: 'manifest.webmanifest',
            scope: '/',
            registerType: 'prompt',
            injectRegister: 'auto',
            includeAssets: [
                'favicon.ico',
                'favicon.svg',
                'apple-touch-icon.png',
                'img/logo-v2-transparent.png',
            ],
            manifest: {
                name: 'Finalyze',
                short_name: 'Finalyze',
                description: 'AI-Powered Academic Project Writing Assistant',
                theme_color: '#0a0a0f',
                background_color: '#0a0a0f',
                display: 'standalone',
                orientation: 'portrait-primary',
                scope: '/',
                start_url: '/',
                categories: ['education', 'productivity'],
                icons: [
                    {
                        src: '/pwa-icons/pwa-64x64.png',
                        sizes: '64x64',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-icons/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-icons/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-icons/maskable-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                // Only precache critical CSS files - use runtime caching for JS
                globPatterns: [
                    'build/assets/*.css',
                ],
                // Ignore large JS bundles - they'll be runtime cached
                globIgnores: [
                    '**/node_modules/**/*',
                    '**/storage/**/*',
                    '**/vendor/**/*',
                    '**/*.map',
                    '**/*.html',
                    '**/pwa-icons/**/*',
                    'build/assets/*.js', // Don't precache JS bundles
                ],
                // Higher limit for the few files we do precache
                maximumFileSizeToCacheInBytes: 5 * 1024 * 1024, // 5MB
                runtimeCaching: [
                    // Runtime cache for JS bundles
                    {
                        urlPattern: /\/build\/assets\/.*\.js$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'js-bundles',
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60 * 24 * 30, // 30 days
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'bunny-fonts',
                            expiration: {
                                maxEntries: 30,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                        },
                    },
                    {
                        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'images',
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 60 * 24 * 30,
                            },
                        },
                    },
                    {
                        urlPattern: /\/api\/.*/,
                        handler: 'NetworkOnly',
                    },
                    {
                        urlPattern: /^https:\/\/.*/i,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'pages',
                            networkTimeoutSeconds: 10,
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                        },
                    },
                ],
                navigateFallback: null,
                skipWaiting: true,
                clientsClaim: true,
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    build: {
        rollupOptions: {
            onwarn(warning, warn) {
                // Suppress manualChunks warning in Vite 7
                if (warning.code === 'INVALID_OPTION') return;
                warn(warning);
            },
            output: {
                // Simplified chunking - function-based for better performance
                manualChunks: (id) => {
                    // Only split the heaviest libraries
                    if (id.includes('mermaid')) {
                        return 'vendor-mermaid';
                    }
                    if (id.includes('@tiptap')) {
                        return 'vendor-tiptap';
                    }
                    // Everything else in one vendor chunk
                    if (id.includes('node_modules')) {
                        return 'vendor';
                    }
                },
            },
        },

        // Use esbuild instead of terser - MUCH faster (10-100x)
        minify: 'esbuild',

        // Reduce chunk size warnings
        chunkSizeWarningLimit: 1500,

        // Target modern browsers for faster builds
        target: 'esnext',

        // Disable source maps in production to save CPU
        sourcemap: false,

        // Reduce CSS processing
        cssMinify: 'esbuild',
    },
});
