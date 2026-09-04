import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const variantStyles = {
    success: 'border-green-200 bg-green-50 text-green-800',
    error: 'border-red-200 bg-red-50 text-red-800',
    info: 'border-[var(--border)] bg-[var(--surface)] text-[var(--text)]',
};

export default function FlashMessage() {
    const { flash } = usePage().props;
    const [banner, setBanner] = useState(null);

    useEffect(() => {
        const message = flash?.error || flash?.success || flash?.message;

        if (!message) {
            setBanner(null);

            return;
        }

        const variant = flash.error ? 'error' : flash.success ? 'success' : 'info';
        setBanner({ message, variant });

        const timer = setTimeout(() => setBanner(null), 6000);

        return () => clearTimeout(timer);
    }, [flash]);

    if (!banner) {
        return null;
    }

    return (
        <div className="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                role="alert"
                className={`border px-4 py-3 text-sm font-medium shadow-lg ${variantStyles[banner.variant]}`}
            >
                {banner.message}
            </div>
        </div>
    );
}
