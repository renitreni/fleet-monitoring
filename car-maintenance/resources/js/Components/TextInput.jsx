import { forwardRef } from 'react';

export default forwardRef(function TextInput({ type = 'text', className = '', variant = 'default', ...props }, ref) {
    const variants = {
        default:
            'mt-1 border-[var(--border)] bg-[var(--surface)] text-[var(--text)] shadow-sm outline-none focus:border-[var(--accent)] focus:ring-[var(--accent)]',
        auth: 'mt-2 h-12 border-[var(--border)] bg-[var(--background)]/60 px-4 text-base text-[var(--text)] outline-none transition placeholder:text-[var(--text-muted)] hover:border-[var(--text-muted)] focus:border-[var(--accent)] focus:ring-1 focus:ring-[var(--accent)] disabled:cursor-not-allowed disabled:opacity-60',
    };

    return <input {...props} type={type} ref={ref} className={'block w-full ' + variants[variant] + ' ' + className} />;
});
