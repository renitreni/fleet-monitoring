import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import TripMap from '@/Components/TripMap';
import RouteLeaderboard from '@/Components/RouteLeaderboard';
import Button from '@/Components/Button';
import { formatTime } from '@/lib/routePreview';

export default function Route({ trip, standings, joined, joinUrl, attemptUrl, personalBest }) {
    const user = usePage().props.auth.user;
    const form = useForm({ public_consent: false });
    return (
        <AuthenticatedLayout
            title={trip.name}
            header={
                <div>
                    <Link href="/routes" className="text-xs font-bold text-[var(--accent)]">
                        ← Browse routes
                    </Link>
                    <h1 className="mt-4 text-4xl font-black uppercase">{trip.name}</h1>
                    <p className="mt-3 text-[var(--text-muted)]">
                        By {trip.creator} · {trip.distance_km} km · {trip.participants_count} joined
                    </p>
                </div>
            }
        >
            <div className="mx-auto max-w-[1384px] space-y-8 px-5 py-8 sm:px-8">
                <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
                    <TripMap
                        points={trip.route_points}
                        checkpoints={trip.checkpoints}
                        showRoutePoints={false}
                        fitOnChange
                    />
                    <aside className="space-y-6 border border-[var(--border)] bg-[var(--surface)] p-6">
                        <p className="eyebrow">{trip.open ? 'Join anytime' : 'Archived route'}</p>
                        <h2 className="text-2xl font-black">Your next personal best.</h2>
                        <p className="text-sm leading-6 text-[var(--text-muted)]">{trip.description}</p>
                        <p className="text-sm leading-6">
                            Timing starts at the verified start point and includes stops. Follow the full course to
                            record a time.
                        </p>
                        {personalBest !== null && (
                            <p className="font-mono text-sm">Your best: {formatTime(personalBest)}</p>
                        )}
                        {joined ? (
                            <Link
                                href={attemptUrl}
                                className="block bg-[var(--accent)] p-4 text-center text-sm font-bold text-white"
                            >
                                {trip.open ? 'Enter route' : 'View my results'} →
                            </Link>
                        ) : !trip.open ? (
                            <p>This route is closed to new attempts.</p>
                        ) : !user ? (
                            <Link
                                href="/login"
                                className="block bg-[var(--accent)] p-4 text-center font-bold text-white"
                            >
                                Sign in to join
                            </Link>
                        ) : (
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.post(joinUrl);
                                }}
                                className="space-y-5"
                            >
                                <label className="flex items-start gap-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.public_consent}
                                        onChange={(event) => form.setData('public_consent', event.target.checked)}
                                    />
                                    <span>
                                        Show my name and best time on the public leaderboard. I can change this anytime.
                                    </span>
                                </label>
                                {Object.values(form.errors).map((error) => (
                                    <p role="alert" key={error}>
                                        {error}
                                    </p>
                                ))}
                                <Button processing={form.processing} className="w-full justify-center">
                                    Join in
                                </Button>
                            </form>
                        )}
                        <p className="text-xs leading-5 text-[var(--text-muted)]">
                            Joining does not start tracking. You choose when to begin.
                        </p>
                    </aside>
                </div>
                <section className="space-y-5">
                    <p className="eyebrow">The times to beat</p>
                    <h2 className="text-3xl font-black uppercase">Route leaderboard</h2>
                    <p className="text-sm text-[var(--text-muted)]">
                        Best completed attempt per driver. Equal times share a rank. Only shared results appear.
                    </p>
                    <RouteLeaderboard rows={standings} />
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
