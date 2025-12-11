import { ref, computed, watch } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

// Singleton State
const appearance = ref<Appearance>('system');
const systemMedia = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
const systemIsDark = ref(systemMedia?.matches || false);

// Helper to update DOM
const LIGHT_THEME_VARS: Record<string, string> = {
    '--background': 'hsl(0 0% 100%)',
    '--foreground': 'hsl(0 0% 3.9%)',
    '--card': 'hsl(0 0% 100%)',
    '--card-foreground': 'hsl(0 0% 3.9%)',
    '--popover': 'hsl(0 0% 100%)',
    '--popover-foreground': 'hsl(0 0% 3.9%)',
    '--primary': 'hsl(0 0% 9%)',
    '--primary-foreground': 'hsl(0 0% 98%)',
    '--secondary': 'hsl(0 0% 92.1%)',
    '--secondary-foreground': 'hsl(0 0% 9%)',
    '--muted': 'hsl(0 0% 96.1%)',
    '--muted-foreground': 'hsl(0 0% 45.1%)',
    '--accent': 'hsl(0 0% 96.1%)',
    '--accent-foreground': 'hsl(0 0% 9%)',
    '--destructive': 'hsl(0 84.2% 60.2%)',
    '--destructive-foreground': 'hsl(0 0% 98%)',
    '--border': 'hsl(0 0% 92.8%)',
    '--input': 'hsl(0 0% 89.8%)',
    '--ring': 'hsl(0 0% 3.9%)',
    '--chart-1': 'hsl(12 76% 61%)',
    '--chart-2': 'hsl(173 58% 39%)',
    '--chart-3': 'hsl(197 37% 24%)',
    '--chart-4': 'hsl(43 74% 66%)',
    '--chart-5': 'hsl(27 87% 67%)',
    '--sidebar-background': 'hsl(0 0% 98%)',
    '--sidebar-foreground': 'hsl(240 5.3% 26.1%)',
    '--sidebar-primary': 'hsl(0 0% 10%)',
    '--sidebar-primary-foreground': 'hsl(0 0% 98%)',
    '--sidebar-accent': 'hsl(0 0% 94%)',
    '--sidebar-accent-foreground': 'hsl(0 0% 30%)',
    '--sidebar-border': 'hsl(0 0% 91%)',
    '--sidebar-ring': 'hsl(217.2 91.2% 59.8%)',
    '--sidebar': 'hsl(0 0% 98%)',
};

const DARK_THEME_VARS: Record<string, string> = {
    '--background': 'hsl(0 0% 3.9%)',
    '--foreground': 'hsl(0 0% 98%)',
    '--card': 'hsl(0 0% 3.9%)',
    '--card-foreground': 'hsl(0 0% 98%)',
    '--popover': 'hsl(0 0% 3.9%)',
    '--popover-foreground': 'hsl(0 0% 98%)',
    '--primary': 'hsl(0 0% 98%)',
    '--primary-foreground': 'hsl(0 0% 9%)',
    '--secondary': 'hsl(0 0% 14.9%)',
    '--secondary-foreground': 'hsl(0 0% 98%)',
    '--muted': 'hsl(0 0% 16.08%)',
    '--muted-foreground': 'hsl(0 0% 63.9%)',
    '--accent': 'hsl(0 0% 14.9%)',
    '--accent-foreground': 'hsl(0 0% 98%)',
    '--destructive': 'hsl(0 84% 60%)',
    '--destructive-foreground': 'hsl(0 0% 98%)',
    '--border': 'hsl(0 0% 14.9%)',
    '--input': 'hsl(0 0% 14.9%)',
    '--ring': 'hsl(0 0% 83.1%)',
    '--chart-1': 'hsl(220 70% 50%)',
    '--chart-2': 'hsl(160 60% 45%)',
    '--chart-3': 'hsl(30 80% 55%)',
    '--chart-4': 'hsl(280 65% 60%)',
    '--chart-5': 'hsl(340 75% 55%)',
    '--sidebar-background': 'hsl(0 0% 7%)',
    '--sidebar-foreground': 'hsl(0 0% 95.9%)',
    '--sidebar-primary': 'hsl(360 100% 100%)',
    '--sidebar-primary-foreground': 'hsl(0 0% 100%)',
    '--sidebar-accent': 'hsl(0 0% 15.9%)',
    '--sidebar-accent-foreground': 'hsl(240 4.8% 95.9%)',
    '--sidebar-border': 'hsl(0 0% 15.9%)',
    '--sidebar-ring': 'hsl(217.2 91.2% 59.8%)',
    '--sidebar': 'hsl(240 5.9% 10%)',
};

function applyThemeVars(vars: Record<string, string>, remove = false) {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;

    Object.entries(vars).forEach(([name, value]) => {
        if (remove) {
            root.style.removeProperty(name);
        } else {
            root.style.setProperty(name, value);
        }
    });
}

function updateDOM(mode: 'light' | 'dark') {
    if (typeof document === 'undefined') return;

    if (mode === 'dark') {
        document.documentElement.classList.add('dark');
        document.documentElement.style.colorScheme = 'dark';
        applyThemeVars(DARK_THEME_VARS);
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';
        applyThemeVars(LIGHT_THEME_VARS);
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

export { LIGHT_THEME_VARS, DARK_THEME_VARS, updateDOM };

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
