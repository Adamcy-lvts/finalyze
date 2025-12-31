import { ref, computed, onMounted, onUnmounted } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const needRefresh = ref(false);
const offlineReady = ref(false);
let swRegistration: ServiceWorkerRegistration | null = null;
let swRegistrationPromise: Promise<ServiceWorkerRegistration | null> | null = null;
let updateListenersAttached = false;

const registerServiceWorker = async () => {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return null;
    }

    if (swRegistration) {
        return swRegistration;
    }

    if (!swRegistrationPromise) {
        swRegistrationPromise = navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                swRegistration = registration;
                return registration;
            })
            .catch((error) => {
                console.error('SW registration error:', error);
                return null;
            });
    }

    return swRegistrationPromise;
};

export function usePWA() {
    const updateServiceWorker = async (reloadPage = true) => {
        if (!swRegistration) {
            return;
        }

        const waitingWorker = swRegistration.waiting;
        if (waitingWorker) {
            waitingWorker.postMessage({ type: 'SKIP_WAITING' });
        }

        if (reloadPage) {
            window.location.reload();
        }
    };

    // Install prompt handling
    const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
    const canInstall = computed(() => deferredPrompt.value !== null);
    const isInstalled = ref(false);

    const handleBeforeInstallPrompt = (e: Event) => {
        e.preventDefault();
        deferredPrompt.value = e as BeforeInstallPromptEvent;
        console.log('PWA: Install prompt available (from event)');
    };

    // Check for prompt captured before Vue mounted
    const checkCapturedPrompt = () => {
        if (typeof window !== 'undefined' && (window as any).deferredPWAPrompt) {
            deferredPrompt.value = (window as any).deferredPWAPrompt as BeforeInstallPromptEvent;
            console.log('PWA: Using pre-captured install prompt');
        }
    };

    const handleAppInstalled = () => {
        deferredPrompt.value = null;
        isInstalled.value = true;
        // Clear the global reference too
        if (typeof window !== 'undefined') {
            (window as any).deferredPWAPrompt = null;
        }
    };

    const installApp = async () => {
        if (!deferredPrompt.value) {
            return false;
        }

        await deferredPrompt.value.prompt();
        const { outcome } = await deferredPrompt.value.userChoice;

        if (outcome === 'accepted') {
            deferredPrompt.value = null;
            isInstalled.value = true;
            return true;
        }

        return false;
    };

    const dismissInstallPrompt = () => {
        deferredPrompt.value = null;
        // Store dismissal preference permanently until cleared
        localStorage.setItem('pwa-install-dismissed', 'true');
    };

    const shouldShowInstallPrompt = computed(() => {
        if (!canInstall.value) {
            return false;
        }

        // Don't show if user has previously dismissed
        const dismissed = localStorage.getItem('pwa-install-dismissed');
        if (dismissed) {
            return false;
        }

        // Show immediately on first visit when install is available
        return true;
    });

    // Online/offline status
    const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);

    const handleOnline = () => {
        isOnline.value = true;
    };

    const handleOffline = () => {
        isOnline.value = false;
    };

    onMounted(() => {
        // Check if already installed (standalone mode)
        if (typeof window !== 'undefined' && window.matchMedia('(display-mode: standalone)').matches) {
            isInstalled.value = true;
        }

        if (typeof window !== 'undefined') {
            registerServiceWorker().then((registration) => {
                if (!registration || updateListenersAttached) {
                    return;
                }

                updateListenersAttached = true;

                // Check for updates every hour
                setInterval(() => {
                    registration.update();
                }, 60 * 60 * 1000);

                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (!newWorker) {
                        return;
                    }

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state !== 'installed') {
                            return;
                        }

                        if (navigator.serviceWorker.controller) {
                            needRefresh.value = true;
                        } else {
                            offlineReady.value = true;
                        }
                    });
                });
            });

            // Check for prompt that was captured before Vue mounted
            checkCapturedPrompt();

            // Listen for future events
            window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
            window.addEventListener('pwa-install-available', checkCapturedPrompt);
            window.addEventListener('appinstalled', handleAppInstalled);
            window.addEventListener('online', handleOnline);
            window.addEventListener('offline', handleOffline);
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
            window.removeEventListener('pwa-install-available', checkCapturedPrompt);
            window.removeEventListener('appinstalled', handleAppInstalled);
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        }
    });

    return {
        // Update handling
        needRefresh,
        offlineReady,
        updateServiceWorker,

        // Install handling
        canInstall,
        isInstalled,
        installApp,
        dismissInstallPrompt,
        shouldShowInstallPrompt,

        // Network status
        isOnline,
    };
}
