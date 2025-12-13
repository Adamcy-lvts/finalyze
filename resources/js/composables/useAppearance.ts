import { computed, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

// Singleton state - shared across all usages
const appearance = ref<Appearance>('system');
let isInitialized = false;

function applyTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    const htmlElement = document.documentElement;
    let shouldBeDark = false;

    if (value === 'system') {
        const mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        shouldBeDark = mediaQueryList.matches;
    } else {
        shouldBeDark = value === 'dark';
    }

    // Use explicit add/remove instead of toggle for more predictable behavior
    if (shouldBeDark) {
        htmlElement.classList.add('dark');
    } else {
        htmlElement.classList.remove('dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const handleSystemThemeChange = () => {
    applyTheme(appearance.value);
};

// Initialize theme once on module load (called from app.ts)
export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Get saved preference from localStorage
    const savedAppearance = getStoredAppearance();

    if (savedAppearance) {
        appearance.value = savedAppearance;
    }

    // Apply the theme
    applyTheme(appearance.value);

    // Set up system theme change listener (only once)
    if (!isInitialized) {
        mediaQuery()?.addEventListener('change', handleSystemThemeChange);
        isInitialized = true;
    }
}

// Legacy export for compatibility
export const updateTheme = applyTheme;

export function useAppearance() {
    // NO onMounted hook here - initialization is done once in app.ts
    // This prevents race conditions when multiple components use this composable

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR
        setCookie('appearance', value);

        // Apply the theme
        applyTheme(value);
    }

    // Computed property to check if dark mode is active
    const isDark = computed(() => {
        if (appearance.value === 'system') {
            return typeof window !== 'undefined'
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                : false;
        }
        return appearance.value === 'dark';
    });

    // Toggle between light and dark (skipping system)
    function toggle() {
        const newAppearance = isDark.value ? 'light' : 'dark';
        updateAppearance(newAppearance);
    }

    return {
        appearance,
        updateAppearance,
        isDark,
        toggle,
    };
}
