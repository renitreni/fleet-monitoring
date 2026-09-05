export default function BrandLogo({
    className = '',
    markClassName = 'h-9 w-9',
    wordmarkClassName = 'text-[var(--text)]',
}) {
    return (
        <span className={`inline-flex items-center gap-3 ${className}`}>
            <svg viewBox="0 0 48 48" fill="none" className={markClassName} aria-hidden="true">
                <path d="M4 4h28l12 12v28H16L4 32V4Z" fill="#ee2b24" />
                <path d="M14 13h14l7 7v14H21l-7-7V13Z" stroke="white" strokeWidth="3.5" />
                <path d="m24 28 10-10" stroke="white" strokeWidth="3.5" />
                <circle cx="24" cy="28" r="2.5" fill="white" />
            </svg>
            <span className={`text-xl font-black tracking-[-0.02em] ${wordmarkClassName}`}>MOTOLOGIC</span>
        </span>
    );
}
