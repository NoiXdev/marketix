import { useEffect, useState } from 'react';

export type Theme = 'light' | 'dark' | 'auto';

const STORAGE_KEY = 'theme';
const MEDIA_QUERY = '(prefers-color-scheme: dark)';

export function resolveIsDark(theme: Theme): boolean {
    if (theme === 'dark') return true;
    if (theme === 'light') return false;
    return window.matchMedia(MEDIA_QUERY).matches;
}

export function getStoredTheme(): Theme {
    try {
        const t = localStorage.getItem(STORAGE_KEY);
        if (t === 'light' || t === 'dark' || t === 'auto') return t;
    } catch {
        // ignore storage access errors
    }
    return 'auto';
}

export function applyTheme(theme: Theme): void {
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // ignore storage access errors
    }
    document.documentElement.classList.toggle('dark', resolveIsDark(theme));
}

export function useTheme(): [Theme, (t: Theme) => void] {
    const [theme, setThemeState] = useState<Theme>(getStoredTheme);

    const setTheme = (t: Theme) => {
        applyTheme(t);
        setThemeState(t);
    };

    useEffect(() => {
        if (theme !== 'auto') return;
        const mql = window.matchMedia(MEDIA_QUERY);
        const onChange = () => applyTheme('auto');
        mql.addEventListener('change', onChange);
        return () => mql.removeEventListener('change', onChange);
    }, [theme]);

    return [theme, setTheme];
}
