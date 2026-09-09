import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import RoutePreview from '@/Components/RoutePreview';
import { formatTime } from '@/lib/routePreview';

export default function Marketplace({ routes, filters, catalogUrl }) {
    const user = usePage().props.auth.user;
    const [search, setSearch] = useState(filters.search);
    const [busy, setBusy] = useState(null);
    const [error, setError] = useState('');
    function browse(next) {
        router.get(catalogUrl, { ...filters, search, ...next }, { preserveState: true, preserveScroll: true });
    }
    function join(route) {
        setBusy(route.id);
        setError('');
        router.post(
            route.join_url,
            {},
            { onError: () => setError('Could not join this route. Please try again.'), onFinish: () => setBusy(null) }
        );
    }
    return (
        <AuthenticatedLayout
            title="Browse routes"
            header={
                <div className="flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p className="eyebrow">Find your next course</p>
                        <h1 className="mt-3 text-4xl font-black uppercase tracking-tight sm:text-5xl">
                            Routes worth taking.
                        </h1>
                        <p className="mt-4 max-w-xl text-[var(--text-muted)]">
                            Explore the course. Join anytime. Make your next attempt a personal best.
                        </p>
                    </div>
                    {user?.is_trip_admin && (
                        <Link href="/admin/trips" className="bg-[var(--accent)] px-6 py-3 text-sm font-bold text-white">
                            + Add route
                        </Link>
                    )}
                </div>
            }
        >
            <div className="mx-auto max-w-[1384px] space-y-8 px-5 py-8 sm:px-8">
                <div className="flex flex-wrap items-center justify-between gap-5">
                    <div className="flex gap-1 border border-[var(--border)] p-1">
                        {[
                            ['all', 'All routes'],
                            ['joined', 'My joined routes'],
                        ]
                            .filter(([key]) => user || key === 'all')
                            .map(([key, label]) => (
                                <button
                                    type="button"
                                    key={key}
                                    onClick={() => browse({ filter: key })}
                                    aria-pressed={filters.filter === key}
                                    className={`px-4 py-2 text-sm font-bold ${filters.filter === key ? 'bg-[var(--text)] text-[var(--background)]' : 'text-[var(--text-muted)]'}`}
                                >
                                    {label}
                                </button>
                            ))}
                    </div>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            browse({});
                        }}
                        className="flex flex-wrap gap-3"
                    >
                        <label className="sr-only" htmlFor="route-search">
                            Search routes
                        </label>
                        <input
                            id="route-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            maxLength={120}
                            placeholder="Search a route…"
                            className="min-w-0 border border-[var(--border)] bg-[var(--surface)] px-4 py-3 text-sm"
                        />
                        <button className="border border-[var(--border)] px-4 text-sm font-bold">Search</button>
                        <label className="sr-only" htmlFor="route-sort">
                            Sort routes
                        </label>
                        <select
                            id="route-sort"
                            value={filters.sort}
                            onChange={(event) => browse({ sort: event.target.value })}
                            className="border border-[var(--border)] bg-[var(--surface)] px-4 py-3 text-sm"
                        >
                            <option value="newest">Newest first</option>
                            <option value="popular">Most joined</option>
                            <option value="shortest">Distance: shortest</option>
                            <option value="longest">Distance: longest</option>
                            <option value="name">Name: A–Z</option>
                        </select>
                    </form>
                </div>
                <div className="flex justify-between text-xs font-bold uppercase tracking-widest text-[var(--text-muted)]">
                    <span>{routes.total} routes to explore</span>
                    <Link href="/trips">My route activity →</Link>
                </div>
                {error && <p role="alert">{error}</p>}
                {!routes.data.length && (
                    <div className="border border-dashed border-[var(--border)] px-6 py-16 text-center">
                        <h2 className="text-xl font-bold">No routes found</h2>
                        <p className="mt-3 text-[var(--text-muted)]">
                            Try a different search or explore all published routes.
                        </p>
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                browse({ search: '', filter: 'all' });
                            }}
                            className="mt-5 font-bold text-[var(--accent)]"
                        >
                            Show all routes →
                        </button>
                    </div>
                )}
                <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    {routes.data.map((route) => (
                        <article
                            key={route.id}
                            className="flex flex-col overflow-hidden border border-[var(--border)] bg-[var(--surface)] transition hover:border-[var(--accent)]"
                        >
                            <Link href={route.url} aria-label={`View ${route.name}`}>
                                <RoutePreview points={route.route_points} name={route.name} />
                            </Link>
                            <div className="flex flex-1 flex-col gap-5 p-6">
                                <div>
                                    <div className="flex justify-between text-[10px] font-bold uppercase tracking-widest text-[var(--accent)]">
                                        <span>{route.open ? 'Open challenge' : 'Archived route'}</span>
                                        <span>{route.distance_km} km</span>
                                    </div>
                                    <h2 className="mt-3 text-2xl font-black">
                                        <Link href={route.url}>{route.name}</Link>
                                    </h2>
                                    <p className="mt-2 line-clamp-2 text-sm text-[var(--text-muted)]">
                                        {route.description || 'A new course to explore, one checkpoint at a time.'}
                                    </p>
                                </div>
                                <div className="mt-auto grid grid-cols-2 gap-3 border-y border-[var(--border)] py-4">
                                    <div>
                                        <p className="text-[10px] uppercase tracking-widest text-[var(--text-muted)]">
                                            Joined
                                        </p>
                                        <p className="mt-1 font-bold">{route.participants_count} drivers</p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] uppercase tracking-widest text-[var(--text-muted)]">
                                            Best shared time
                                        </p>
                                        <p className="mt-1 font-mono text-sm font-bold">
                                            {formatTime(route.best_time)}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <Link href={route.url} className="text-xs font-bold">
                                        View leaderboard ↗
                                    </Link>
                                    {route.joined ? (
                                        <Link
                                            href={route.attempt_url}
                                            className="bg-[var(--accent)] px-5 py-3 text-xs font-black uppercase text-white"
                                        >
                                            {route.open ? 'Enter route' : 'View results'}
                                        </Link>
                                    ) : user ? (
                                        <button
                                            type="button"
                                            disabled={busy !== null || !route.open}
                                            onClick={() => join(route)}
                                            className="bg-[var(--accent)] px-5 py-3 text-xs font-black uppercase text-white disabled:opacity-50"
                                        >
                                            {busy === route.id ? 'Joining…' : 'Join in'}
                                        </button>
                                    ) : (
                                        <Link
                                            href={route.url}
                                            className="bg-[var(--accent)] px-5 py-3 text-xs font-black uppercase text-white"
                                        >
                                            Join in
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
                <nav aria-label="Route pages" className="flex items-center justify-between text-sm font-bold">
                    <span>
                        Page {routes.current_page} of {routes.last_page}
                    </span>
                    <div className="flex gap-6">
                        {routes.prev_page_url && <Link href={routes.prev_page_url}>← Previous</Link>}
                        {routes.next_page_url && <Link href={routes.next_page_url}>Next →</Link>}
                    </div>
                </nav>
            </div>
        </AuthenticatedLayout>
    );
}
