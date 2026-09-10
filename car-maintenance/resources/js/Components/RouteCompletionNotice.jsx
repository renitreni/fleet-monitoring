import { useEffect, useState } from 'react';

export default function RouteCompletionNotice() {
    const [compact, setCompact] = useState(false);

    useEffect(() => {
        const timeout = window.setTimeout(() => setCompact(true), 10000);
        return () => window.clearTimeout(timeout);
    }, []);

    return (
        <div className="pointer-events-none fixed inset-x-0 top-[max(1rem,env(safe-area-inset-top))] z-[1000] flex justify-center px-4">
            <div
                role="status"
                aria-live="polite"
                aria-atomic="true"
                className={`route-completion flex items-center border-2 border-emerald-200 bg-emerald-950 text-white shadow-2xl ${
                    compact ? 'gap-3 px-5 py-3' : 'w-full max-w-xl gap-5 px-6 py-7 sm:p-8'
                }`}
            >
                <svg
                    aria-hidden="true"
                    viewBox="0 0 64 64"
                    fill="none"
                    className={`shrink-0 text-emerald-300 ${compact ? 'h-8 w-8' : 'h-16 w-16 sm:h-20 sm:w-20'}`}
                >
                    <circle
                        className="route-completion-ring"
                        cx="32"
                        cy="32"
                        r="28"
                        stroke="currentColor"
                        strokeWidth="4"
                    />
                    <path
                        className="route-completion-check"
                        d="m18 32 9 9 19-19"
                        pathLength="1"
                        stroke="currentColor"
                        strokeWidth="5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
                <div>
                    <p className={`font-black uppercase leading-tight ${compact ? 'text-lg' : 'text-3xl sm:text-4xl'}`}>
                        Route complete
                    </p>
                    {!compact && (
                        <p className="mt-2 text-base text-emerald-100">All checkpoints verified. Tracking stopped.</p>
                    )}
                </div>
            </div>
        </div>
    );
}
