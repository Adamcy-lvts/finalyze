import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRegisterSW } from 'virtual:pwa-register/vue';

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

export function usePWA() {
    const { needRefresh, offlineReady, updateServiceWorker } = useRegisterSW({
        onRegistered(registration) {
            if (registration) {
                // Check for updates every hour
                setInterval(
                    () => {
                        registration.update();
                    },
                    60 * 60 * 1000,
                );
            }
        },
        onRegisterError(error) {
            console.error('SW registration error:', error);
        },
    });

    // Install prompt handling
    const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
    const canInstall = computed(() => deferredPrompt.value !== null);
    const isInstalled = ref(false);

    const handleBeforeInstallPrompt = (e: Event) => {
        e.preventDefault();
        deferredPrompt.value = e as BeforeInstallPromptEvent;
    };

    const handleAppInstalled = () => {
        deferredPrompt.value = null;
        isInstalled.value = true;
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
        // Store dismissal preference
        localStorage.setItem('pwa-install-dismissed', Date.now().toString());
    };

    const shouldShowInstallPrompt = computed(() => {
        if (!canInstall.value) {
            return false;
        }

        const dismissed = localStorage.getItem('pwa-install-dismissed');
        if (dismissed) {
            // Don't show for 7 days after dismissal
            const dismissedAt = parseInt(dismissed, 10);
            const sevenDays = 7 * 24 * 60 * 60 * 1000;
            if (Date.now() - dismissedAt < sevenDays) {
                return false;
            }
        }

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
            window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
            window.addEventListener('appinstalled', handleAppInstalled);
            window.addEventListener('online', handleOnline);
            window.addEventListener('offline', handleOffline);
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
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
