export default function Button({
    type = 'submit',
    className = '',
    disabled,
    processing,
    variant = 'primary',
    children,
    ...props
}) {
    const baseStyles =
        'inline-flex items-center border px-4 py-2 text-xs font-black uppercase tracking-widest transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-[var(--accent)] focus:ring-offset-2 focus:ring-offset-[var(--background)]';

    const variants = {
        primary: 'border-[var(--accent)] bg-[var(--accent)] text-white hover:brightness-110 active:brightness-90',
        secondary: 'border-[var(--border)] bg-[var(--surface)] text-[var(--text)] hover:border-[var(--accent)]',
        danger: 'border-transparent bg-red-600 text-white hover:bg-red-500 active:bg-red-700',
        auth: 'border-[var(--accent)] bg-[var(--accent)] text-white hover:brightness-110 active:brightness-90',
    };

    return (
        <button
            {...props}
            type={type}
            disabled={disabled || processing}
            className={
                baseStyles +
                ' ' +
                variants[variant] +
                ' ' +
                (disabled || processing ? 'opacity-50 cursor-not-allowed ' : '') +
                className
            }
        >
            {children}
        </button>
    );
}
