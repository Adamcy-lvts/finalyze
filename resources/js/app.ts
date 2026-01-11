import '../css/app.css';
import './polyfills';
import 'vue-sonner/style.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from '@/composables/useAppearance';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { router } from '@inertiajs/vue3';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo;
        GA_MEASUREMENT_ID?: string;
        gtag?: (command: string, ...args: any[]) => void;
        deferredPWAPrompt?: BeforeInstallPromptEvent | null;
    }
}

window.Pusher = Pusher;

// Enable debug mode only in development
Pusher.logToConsole = import.meta.env.DEV;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Configure Echo with environment-aware settings
// const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || 'https';
// const isSecure = reverbScheme === 'https';

// window.Echo = new Echo({
//     broadcaster: 'reverb',
//     key: import.meta.env.VITE_REVERB_APP_KEY,
//     wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
//     wsPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : (isSecure ? 443 : 80),
//     wssPort: import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT) : 443,
//     forceTLS: isSecure,
//     enabledTransports: ['ws', 'wss'],
//     authEndpoint: '/broadcasting/auth',
//     auth: {
//         headers: {
//             'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
//         },
//     },
// });

// Configure axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true;
axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (event: Event) => {
        event.preventDefault();
        window.deferredPWAPrompt = event as BeforeInstallPromptEvent;
        window.dispatchEvent(new Event('pwa-install-available'));
    });

    window.addEventListener('appinstalled', () => {
        window.deferredPWAPrompt = null;
    });
}

function updateCsrfToken(token?: string) {
    if (!token) return;

    const meta = document.head.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.setAttribute('content', token);
    }

    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const gaMeasurementId = typeof window !== 'undefined' ? window.GA_MEASUREMENT_ID : undefined;

function trackPageView(url: string) {
    if (!gaMeasurementId || typeof window === 'undefined' || typeof window.gtag !== 'function') {
        return;
    }

    const pageLocation = new URL(url, window.location.origin).toString();

    window.gtag('event', 'page_view', {
        page_location: pageLocation,
        page_path: url,
        page_title: document.title,
        send_to: gaMeasurementId,
    });
}

// createInertiaApp({
//     title: (title) => (title ? `${title} - ${appName}` : appName),
//     resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
//     setup({ el, App, props, plugin }) {
//         updateCsrfToken((props.initialPage.props as any)?.csrf_token);

//         router.on('navigate', (event) => {
//             updateCsrfToken((event.detail.page.props as any)?.csrf_token);
//         });

//         // Apply theme as early as possible during initial mount.
//         initializeTheme();

//         // Re-apply theme after each Inertia navigation to avoid stale DOM theme state.
//         router.on('finish', () => {
//             initializeTheme();
//         });

//         createApp({ render: () => h(App, props) })
//             .use(plugin)
//             .use(ZiggyVue)
//             .mount(el);
//     },
//     progress: {
//         color: '#4B5563',
//     },
// });

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Set up Ziggy routes globally for direct imports
        const ziggyConfig = (props.initialPage.props as any).ziggy;
        if (typeof window !== 'undefined') {
            (window as any).Ziggy = ziggyConfig;
        }

        updateCsrfToken((props.initialPage.props as any)?.csrf_token);

        router.on('navigate', (event) => {
            updateCsrfToken((event.detail.page.props as any)?.csrf_token);
            // Update Ziggy routes on navigation
            const newZiggyConfig = (event.detail.page.props as any)?.ziggy;
            if (newZiggyConfig && typeof window !== 'undefined') {
                (window as any).Ziggy = newZiggyConfig;
            }
        });

        // Re-apply theme after each Inertia navigation to avoid stale DOM theme state
        router.on('finish', (event) => {
            initializeTheme();
            if (event.detail.page?.url) {
                trackPageView(event.detail.page.url);
            }
        });


        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, ziggyConfig)
            .mount(el);

        trackPageView(window.location.pathname + window.location.search + window.location.hash);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
