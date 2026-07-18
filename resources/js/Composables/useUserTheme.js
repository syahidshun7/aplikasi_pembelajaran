import { onBeforeUnmount, onMounted, ref } from 'vue';

export const USER_THEME_STORAGE_KEY = 'dooptech-user-theme';
export const USER_THEME_EVENT = 'dooptech:user-theme-change';

const normalizeTheme = (value) => (String(value || '').toLowerCase() === 'dark' ? 'dark' : 'light');

const readStoredTheme = () => {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return normalizeTheme(window.localStorage.getItem(USER_THEME_STORAGE_KEY));
};

const sharedTheme = ref(readStoredTheme());

export function setUserTheme(nextTheme, options = {}) {
    const { persist = true, broadcast = true } = options;
    const normalizedTheme = normalizeTheme(nextTheme);
    sharedTheme.value = normalizedTheme;

    if (typeof window === 'undefined') {
        return;
    }

    if (persist) {
        window.localStorage.setItem(USER_THEME_STORAGE_KEY, normalizedTheme);
    }

    if (broadcast) {
        window.dispatchEvent(new CustomEvent(USER_THEME_EVENT, { detail: { theme: normalizedTheme } }));
    }
}

export function useUserTheme() {
    const syncThemeFromStorage = (event) => {
        if (event.key !== USER_THEME_STORAGE_KEY) {
            return;
        }

        setUserTheme(event.newValue, { persist: false, broadcast: false });
    };

    const syncThemeFromBroadcast = (event) => {
        setUserTheme(event?.detail?.theme, { persist: false, broadcast: false });
    };

    onMounted(() => {
        setUserTheme(readStoredTheme(), { persist: false, broadcast: false });
        window.addEventListener('storage', syncThemeFromStorage);
        window.addEventListener(USER_THEME_EVENT, syncThemeFromBroadcast);
    });

    onBeforeUnmount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.removeEventListener('storage', syncThemeFromStorage);
        window.removeEventListener(USER_THEME_EVENT, syncThemeFromBroadcast);
    });

    const toggleUserTheme = () => {
        setUserTheme(sharedTheme.value === 'light' ? 'dark' : 'light');
    };

    return {
        themeMode: sharedTheme,
        setUserTheme,
        toggleUserTheme,
    };
}
