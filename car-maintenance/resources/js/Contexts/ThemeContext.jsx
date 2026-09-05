import { createContext, useContext, useEffect, useMemo, useState } from 'react';

const ThemeContext = createContext(null);
const storageKey = 'motologic-theme';

function getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
    const root = document.documentElement;

    root.classList.toggle('dark', theme === 'dark');
    root.style.colorScheme = theme;

    document
        .querySelector('meta[name="theme-color"]')
        ?.setAttribute('content', theme === 'dark' ? '#090b0d' : '#f3f1ec');
}

export function ThemeProvider({ children }) {
    const [theme, setThemeState] = useState(() => {
        if (typeof window === 'undefined') {
            return 'light';
        }

        return window.localStorage.getItem(storageKey) || getSystemTheme();
    });
    const [hasOverride, setHasOverride] = useState(() => Boolean(window.localStorage.getItem(storageKey)));

    useEffect(() => {
        applyTheme(theme);
    }, [theme]);

    useEffect(() => {
        if (hasOverride) {
            return undefined;
        }

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => setThemeState(getSystemTheme());

        mediaQuery.addEventListener('change', handleChange);

        return () => mediaQuery.removeEventListener('change', handleChange);
    }, [hasOverride]);

    const value = useMemo(
        () => ({
            theme,
            setTheme(nextTheme) {
                window.localStorage.setItem(storageKey, nextTheme);
                setHasOverride(true);
                setThemeState(nextTheme);
            },
            toggleTheme() {
                const nextTheme = theme === 'dark' ? 'light' : 'dark';
                window.localStorage.setItem(storageKey, nextTheme);
                setHasOverride(true);
                setThemeState(nextTheme);
            },
        }),
        [theme]
    );

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
    const context = useContext(ThemeContext);

    if (!context) {
        throw new Error('useTheme must be used within ThemeProvider');
    }

    return context;
}
