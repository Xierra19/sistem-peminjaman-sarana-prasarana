import { ref, watch } from 'vue';

const STORAGE_KEY = 'theme';
const isBrowser = typeof window !== 'undefined' && typeof document !== 'undefined';
const systemThemeQuery = isBrowser ? window.matchMedia('(prefers-color-scheme: dark)') : null;

const resolveInitialDarkMode = () => {
    if (!isBrowser) {
        return false;
    }

    const storedTheme = window.localStorage.getItem(STORAGE_KEY);

    if (storedTheme === 'dark') {
        return true;
    }

    if (storedTheme === 'light') {
        return false;
    }

    return systemThemeQuery?.matches ?? false;
};

const isDark = ref(resolveInitialDarkMode());
let hasBootstrapped = false;

const applyTheme = (dark) => {
    if (!isBrowser) {
        return;
    }

    document.documentElement.classList.toggle('dark', dark);
};

const syncWithSystemTheme = (event) => {
    if (!isBrowser) {
        return;
    }

    const storedTheme = window.localStorage.getItem(STORAGE_KEY);

    if (storedTheme !== 'dark' && storedTheme !== 'light') {
        isDark.value = event.matches;
    }
};

export function initializeDarkMode() {
    if (!isBrowser || hasBootstrapped) {
        return;
    }

    hasBootstrapped = true;
    isDark.value = resolveInitialDarkMode();
    applyTheme(isDark.value);

    watch(
        isDark,
        (dark) => {
            applyTheme(dark);
            window.localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        },
        { immediate: true },
    );

    systemThemeQuery?.addEventListener('change', syncWithSystemTheme);
}

/**
 * Composable untuk mengelola status mode gelap (dark mode) aplikasi.
 * Menyimpan preferensi pengguna di localStorage dan mendeteksi preferensi sistem operasi.
 */
export function useDarkMode() {
    initializeDarkMode();

    const toggleDarkMode = () => {
        isDark.value = !isDark.value;
    };

    return {
        isDark,
        toggleDarkMode,
    };
}
