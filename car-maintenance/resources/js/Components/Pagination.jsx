import { Link } from '@inertiajs/react';

export default function Pagination({ pagination, ariaLabel = 'Pagination', itemLabel = 'articles' }) {
    if (pagination.total === 0) return null;

    return (
        <nav
            aria-label={ariaLabel}
            className="mt-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p className="text-xs text-[var(--text-muted)]">
                {pagination.from ? (
                    <>
                        Showing {pagination.from}–{pagination.to} of {pagination.total} {itemLabel}
                    </>
                ) : (
                    <>
                        {pagination.total} {itemLabel}
                    </>
                )}
            </p>
            <div className="flex flex-wrap gap-2">
                {pagination.links.map((link, index) => {
                    const className = `border px-4 py-2 text-xs font-black uppercase ${
                        link.active
                            ? 'border-[var(--accent)] bg-[var(--accent)] text-white'
                            : 'border-[var(--border)] bg-[var(--surface)] text-[var(--text)]'
                    }`;

                    if (link.active || !link.url) {
                        return (
                            <span
                                key={index}
                                aria-current={link.active ? 'page' : undefined}
                                aria-disabled={!link.url ? true : undefined}
                                className={`${className} ${!link.url ? 'opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        );
                    }

                    return (
                        <Link
                            key={index}
                            href={link.url}
                            className={`${className} transition hover:border-[var(--accent)] hover:text-[var(--accent)]`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>
        </nav>
    );
}
