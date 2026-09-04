export default function EmptyState({ icon = '🚗', title, description, action = null, className = '' }) {
    return (
        <div className={`border border-[var(--border)] bg-[var(--surface)] p-12 text-center shadow-sm ${className}`}>
            <div className="text-4xl" aria-hidden="true">
                {icon}
            </div>
            <h3 className="mt-4 text-lg font-bold uppercase text-[var(--text)]">{title}</h3>
            {description && <p className="mx-auto mt-2 max-w-md text-sm text-[var(--text-muted)]">{description}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
