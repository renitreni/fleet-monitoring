import './bootstrap';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import ErrorBoundary from '@/Components/ErrorBoundary';
import { ThemeProvider } from '@/Contexts/ThemeContext';

createInertiaApp({
    title: (title) => `${title} - ${import.meta.env.VITE_APP_NAME ?? 'Motologiq'}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <ThemeProvider>
                    <ErrorBoundary>
                        <App {...props} />
                    </ErrorBoundary>
                </ThemeProvider>
            </StrictMode>
        );
    },
});
