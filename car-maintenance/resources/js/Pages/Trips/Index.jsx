import { Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ trips }) {
    const user = usePage().props.auth.user;
    return (
        <AuthenticatedLayout
            title="Road trips"
            header={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="eyebrow">Together on the road</p>
                        <h1 className="mt-2 text-3xl font-black uppercase">Your road trips</h1>
                    </div>
                    {user.is_trip_admin && (
                        <Link
                            href="/admin/trips"
                            className="bg-[var(--accent)] px-5 py-3 text-xs font-black uppercase tracking-widest text-white"
                        >
                            Trip control panel
                        </Link>
                    )}
                </div>
            }
        >
            <div className="mx-auto max-w-6xl space-y-6 px-5 py-10">
                <p className="text-[var(--text-muted)]">
                    Open an invitation from your trip organizer to join. Your maintenance records are always available
                    in My cars.
                </p>
                {!trips.data.length && (
                    <div className="border border-dashed border-[var(--border)] p-12 text-center">
                        <h2 className="text-xl font-bold">Your next group drive starts here</h2>
                        <p className="mt-3 text-[var(--text-muted)]">
                            Ask your organizer for an invitation link. Joining never starts location tracking
                            automatically.
                        </p>
                    </div>
                )}
                <div className="grid gap-4 sm:grid-cols-2">
                    {trips.data.map((trip) => (
                        <Link
                            key={trip.id}
                            href={trip.url}
                            className="border border-[var(--border)] bg-[var(--surface)] p-6 hover:border-[var(--accent)]"
                        >
                            <p className="text-xs font-bold uppercase text-[var(--accent)]">
                                {trip.open ? 'Open trip' : 'Trip ended'}
                            </p>
                            <h2 className="mt-3 text-2xl font-black">{trip.name}</h2>
                            <p className="mt-3 text-sm text-[var(--text-muted)]">
                                {trip.distance_km} km planned route →
                            </p>
                        </Link>
                    ))}
                </div>
                <div className="flex gap-4">
                    {trips.prev_page_url && <Link href={trips.prev_page_url}>← Previous</Link>}
                    {trips.next_page_url && <Link href={trips.next_page_url}>Next →</Link>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
