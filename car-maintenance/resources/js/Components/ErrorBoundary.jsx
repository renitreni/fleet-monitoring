import { Component } from 'react';

export default class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
        if (import.meta.env.DEV) {
            console.error('ErrorBoundary caught an error:', error, errorInfo);
        }
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="flex min-h-screen flex-col items-center justify-center bg-[var(--background)] px-4 text-[var(--text)]">
                    <div className="w-full max-w-md border border-[var(--border)] bg-[var(--surface)] p-8 text-center shadow-md">
                        <div className="text-4xl" aria-hidden="true">
                            ⚠️
                        </div>
                        <h1 className="mt-4 text-xl font-bold uppercase text-[var(--text)]">Something went wrong</h1>
                        <p className="mt-2 text-sm text-[var(--text-muted)]">
                            An unexpected error occurred. Please try refreshing the page.
                        </p>
                        {import.meta.env.DEV && this.state.error && (
                            <pre className="mt-4 max-h-40 overflow-auto bg-[var(--surface-muted)] p-3 text-left text-xs text-red-600 dark:text-red-400">
                                {this.state.error.toString()}
                            </pre>
                        )}
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="mt-6 inline-flex items-center border border-[var(--accent)] bg-[var(--accent)] px-4 py-2 text-sm font-bold uppercase text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
                        >
                            Refresh page
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
