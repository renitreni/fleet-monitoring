import process from 'node:process';
import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { renderToString } from 'react-dom/server';
import { StrictMode } from 'react';
import ErrorBoundary from '@/Components/ErrorBoundary';
import { ThemeProvider } from '@/Contexts/ThemeContext';

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => `${title} - ${import.meta.env.VITE_APP_NAME ?? 'Motologic'}`,
            resolve: (name) => {
                const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
                return pages[`./Pages/${name}.jsx`];
            },
            setup: ({ App, props }) => (
                <StrictMode>
                    <ThemeProvider>
                        <ErrorBoundary>
                            <App {...props} />
                        </ErrorBoundary>
                    </ThemeProvider>
                </StrictMode>
            ),
        }),
    { port: Number(process.env.SSR_PORT || 13714) }
);
