import { ref, computed, watch } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

// Singleton State
const appearance = ref<Appearance>('system');
const systemMedia = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
const systemIsDark = ref(systemMedia?.matches || false);

// Helper to update DOM
function updateDOM(mode: 'light' | 'dark') {
    if (typeof document === 'undefined') return;

    if (mode === 'dark') {
        document.documentElement.classList.add('dark');
        document.documentElement.style.colorScheme = 'dark';
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';
    }
}

// Calculate effective mode
const effectiveMode = computed(() => {
    if (appearance.value === 'system') {
        return systemIsDark.value ? 'dark' : 'light';
    }
    return appearance.value;
});

// Watch for changes and update DOM
watch(effectiveMode, (newMode) => {
    updateDOM(newMode);
}, { immediate: true });

// Initialize from storage
if (typeof window !== 'undefined') {
    const stored = localStorage.getItem('appearance') as Appearance | null;
    if (stored) {
        appearance.value = stored;
    }

    // Listen for system preference changes
    systemMedia?.addEventListener('change', (e) => {
        systemIsDark.value = e.matches;
    });
}

export function initializeTheme() {
    // Initialization is handled by top-level code, but we ensure DOM is synced
    if (typeof window !== 'undefined') {
        updateDOM(effectiveMode.value);
    }
}

export function updateTheme(value: Appearance) {
    appearance.value = value;
    if (typeof window !== 'undefined') {
        localStorage.setItem('appearance', value);
    }
}

export function useAppearance() {
    const isDark = computed(() => effectiveMode.value === 'dark');

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        if (typeof window !== 'undefined') {
            localStorage.setItem('appearance', value);

            // Set cookie for SSR
            const maxAge = 365 * 24 * 60 * 60;
            document.cookie = `appearance=${value};path=/;max-age=${maxAge};SameSite=Lax`;
        }
    }

    function toggle() {
        updateAppearance(isDark.value ? 'light' : 'dark');
    }

    return {
        appearance,
        isDark,
        updateAppearance,
        toggle
    };
}
