export default function LoadingSkeleton({ className = '', lines = 3 }) {
    return (
        <div className={`animate-pulse ${className}`} aria-busy="true" aria-label="Loading">
            {Array.from({ length: lines }).map((_, index) => (
                <div
                    key={index}
                    className="mb-3 h-4 bg-[var(--surface-muted)] last:mb-0"
                    style={{ width: index === lines - 1 ? '60%' : '100%' }}
                />
            ))}
        </div>
    );
}
