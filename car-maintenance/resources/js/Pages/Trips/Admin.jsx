import { useEffect, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import Label from '@/Components/Label';
import TripMap from '@/Components/TripMap';
import { findDrivingRoute, parseEndpoint } from '@/lib/tripRouting';

export default function Admin({ trips, storeUrl }) {
    const [endpoints, setEndpoints] = useState({ start: '', end: '' });
    const [selectedEndpoint, setSelectedEndpoint] = useState('start');
    const [route, setRoute] = useState(null);
    const [routeError, setRouteError] = useState('');
    const [routing, setRouting] = useState(false);
    const [retry, setRetry] = useState(0);
    const [notice, setNotice] = useState('');
    const [busy, setBusy] = useState(false);
    const form = useForm({ name: '', description: '', is_public: false, ends_at: '' });
    const routeKey = JSON.stringify(endpoints);
    const currentRoute = route?.key === routeKey ? route : null;
    const points = currentRoute?.points ?? [];
    const checkpoints = points.length
        ? [
              { name: 'Start', point_index: 0 },
              { name: 'Finish', point_index: points.length - 1 },
          ]
        : [];
    const start = parseEndpoint(endpoints.start);
    const end = parseEndpoint(endpoints.end);
    const endpointMarkers = [
        ...(start ? [{ ...start, name: 'Start', label: 'S' }] : []),
        ...(end ? [{ ...end, name: 'Finish', label: 'E' }] : []),
    ];
    useEffect(() => {
        const values = JSON.parse(routeKey);
        const startPoint = parseEndpoint(values.start);
        const endPoint = parseEndpoint(values.end);
        setRoute(null);
        setRouteError('');
        if (!startPoint || !endPoint) {
            setRouting(false);
            return;
        }
        const controller = new AbortController();
        let active = true;
        let timeout;
        setRouting(true);
        const timer = setTimeout(async () => {
            timeout = setTimeout(() => {
                if (!active) return;
                active = false;
                controller.abort();
                setRouting(false);
                setRouteError('Route calculation timed out. Please retry.');
            }, 20000);
            try {
                const result = await findDrivingRoute(startPoint, endPoint, controller.signal);
                if (active) setRoute({ ...result, key: routeKey });
            } catch (error) {
                if (active) setRouteError(error.message || 'Route calculation failed. Please retry.');
            } finally {
                clearTimeout(timeout);
                if (active) setRouting(false);
            }
        }, 800);
        return () => {
            active = false;
            clearTimeout(timer);
            clearTimeout(timeout);
            controller.abort();
        };
    }, [routeKey, retry]);
    function selectPoint(point) {
        setEndpoints((current) => ({
            ...current,
            [selectedEndpoint]: `${point.latitude.toFixed(6)}, ${point.longitude.toFixed(6)}`,
        }));
        if (selectedEndpoint === 'start') setSelectedEndpoint('end');
    }
    function submit(event) {
        event.preventDefault();
        if (!currentRoute || routing) return;
        form.transform((data) => ({
            ...data,
            ends_at: data.ends_at ? new Date(data.ends_at).toISOString() : '',
            route_points: points.map(({ latitude, longitude }) => ({ latitude, longitude })),
            checkpoints,
        })).post(storeUrl, {
            onSuccess: () => {
                form.reset();
                setEndpoints({ start: '', end: '' });
                setSelectedEndpoint('start');
                setRoute(null);
                setNotice('Trip created. Copy its invitation from the list below.');
            },
        });
    }
    function manage(trip, action, extra = {}) {
        if (
            action === 'close' &&
            !window.confirm('End this trip for everyone? Tracking and invitations will stop. This cannot be undone.')
        )
            return;
        if (
            action === 'rotate' &&
            !window.confirm('Replace this invitation? Previously shared links will stop working.')
        )
            return;
        setBusy(true);
        router.patch(trip.manage_url, { action, ...extra }, { preserveScroll: true, onFinish: () => setBusy(false) });
    }
    async function copy(url) {
        try {
            await navigator.clipboard.writeText(url);
            setNotice('Invitation copied.');
        } catch {
            setNotice('Copy the invitation from the link field below.');
        }
    }
    return (
        <AuthenticatedLayout
            title="Trip control panel"
            header={
                <div>
                    <p className="eyebrow">Organizer workspace</p>
                    <h1 className="mt-2 text-3xl font-black uppercase">Trip control panel</h1>
                    <p className="mt-3 text-sm text-[var(--text-muted)]">Plan the course. Bring the group together.</p>
                </div>
            }
        >
            <div className="mx-auto max-w-[1384px] space-y-10 px-5 py-8 sm:px-8">
                <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[1fr_340px]">
                    <div className="space-y-5">
                        <div>
                            <h2 className="text-xl font-black uppercase">01 / Choose your route</h2>
                            <p className="mt-2 text-sm leading-6 text-[var(--text-muted)]">
                                Enter start and end coordinates, or select each endpoint on the map. The shortest
                                driving route is highlighted automatically. Drag the map to explore.
                            </p>
                        </div>
                        {['start', 'end'].map((endpoint) => (
                            <div key={endpoint} className="flex flex-wrap items-end gap-3">
                                <div className="min-w-48 flex-1">
                                    <Label htmlFor={`route-${endpoint}`}>
                                        {endpoint === 'start' ? 'Start point' : 'Endpoint'}
                                    </Label>
                                    <TextInput
                                        id={`route-${endpoint}`}
                                        value={endpoints[endpoint]}
                                        placeholder="Latitude, longitude (e.g. 14.5995, 120.9842)"
                                        onFocus={() => setSelectedEndpoint(endpoint)}
                                        onChange={(event) =>
                                            setEndpoints((current) => ({ ...current, [endpoint]: event.target.value }))
                                        }
                                        aria-invalid={Boolean(
                                            endpoints[endpoint] && !parseEndpoint(endpoints[endpoint])
                                        )}
                                        aria-describedby={`route-${endpoint}-help`}
                                        className="border p-3"
                                    />
                                    <p id={`route-${endpoint}-help`} className="mt-1 text-xs text-[var(--text-muted)]">
                                        {endpoints[endpoint] && !parseEndpoint(endpoints[endpoint])
                                            ? 'Enter valid latitude and longitude separated by a comma.'
                                            : 'Latitude first, then longitude.'}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    aria-pressed={selectedEndpoint === endpoint}
                                    onClick={() => setSelectedEndpoint(endpoint)}
                                >
                                    {selectedEndpoint === endpoint ? 'Selecting on map' : 'Select on map'}
                                </Button>
                            </div>
                        ))}
                        <TripMap
                            points={points}
                            checkpoints={checkpoints}
                            markers={currentRoute ? [] : endpointMarkers}
                            onAdd={selectPoint}
                            showRoutePoints={false}
                            fitOnChange
                            selectionHint={`Tap to set ${selectedEndpoint === 'start' ? 'start point' : 'endpoint'} · Drag to pan`}
                            focusPoint={selectedEndpoint === 'start' ? start : end}
                        />
                        <div role="status" aria-live="polite" className="text-sm text-[var(--text-muted)]">
                            {routing
                                ? 'Finding the shortest driving route…'
                                : currentRoute
                                  ? `${currentRoute.distanceKm.toFixed(2)} km driving route · Start and finish set automatically`
                                  : !routeError
                                    ? 'Set both endpoints to see your route.'
                                    : ''}
                        </div>
                        {routeError && (
                            <div role="alert" className="space-y-2 text-sm text-red-600 dark:text-red-400">
                                <p>{routeError}</p>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setRetry((value) => value + 1)}
                                >
                                    Retry route
                                </Button>
                            </div>
                        )}
                        <p className="text-xs text-[var(--text-muted)]">
                            Routing by{' '}
                            <a
                                className="underline"
                                href="https://valhalla.openstreetmap.de/"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Valhalla / FOSSGIS
                            </a>
                            . Endpoints snap to nearby roads. An internet connection is required.
                        </p>
                    </div>
                    <div className="space-y-5">
                        <h2 className="text-xl font-black uppercase">02 / Set up the trip</h2>
                        <div>
                            <Label htmlFor="trip-name">Trip name</Label>
                            <TextInput
                                id="trip-name"
                                required
                                maxLength={120}
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                placeholder="Sunday coastal drive"
                                className="border p-3"
                            />
                        </div>
                        <div>
                            <Label htmlFor="description">Meet-up details</Label>
                            <textarea
                                id="description"
                                maxLength={2000}
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                className="mt-1 min-h-28 w-full border border-[var(--border)] bg-[var(--surface)] p-3"
                                placeholder="Where to meet and what to bring"
                            />
                        </div>
                        <div>
                            <Label htmlFor="ends-at">Trip and invitation end time</Label>
                            <TextInput
                                id="ends-at"
                                type="datetime-local"
                                required
                                value={form.data.ends_at}
                                onChange={(event) => form.setData('ends_at', event.target.value)}
                                className="border p-3"
                            />
                            <p className="mt-2 text-xs text-[var(--text-muted)]">
                                Your local time. Choose within the next 30 days.
                            </p>
                        </div>
                        <label className="flex items-start gap-3 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.is_public}
                                onChange={(event) => form.setData('is_public', event.target.checked)}
                            />
                            <span>
                                Feature this trip on the landing page with my account name as route creator. Only
                                participants who opt in appear on its public leaderboard.
                            </span>
                        </label>
                        <div className="border-l-2 border-[var(--accent)] bg-[var(--surface)] p-4 text-sm leading-6 text-[var(--text-muted)]">
                            Start and finish are added automatically. Routes must be between 100 metres and 500
                            kilometres. The course is fixed after creation. Review the map before saving.
                        </div>
                        {Object.keys(form.errors).length > 0 && (
                            <ul role="alert" className="space-y-2 text-sm text-red-600 dark:text-red-400">
                                {Object.entries(form.errors).map(([key, message]) => (
                                    <li key={key}>{message}</li>
                                ))}
                            </ul>
                        )}
                        <Button
                            processing={form.processing}
                            disabled={!currentRoute || routing}
                            className="w-full justify-center py-4"
                        >
                            Create trip & invitation
                        </Button>
                    </div>
                </form>
                <section className="space-y-5 border-t border-[var(--border)] pt-8">
                    <h2 className="text-2xl font-black uppercase">Manage trips</h2>
                    {notice && (
                        <p role="status" className="text-sm text-[var(--accent)]">
                            {notice}
                        </p>
                    )}
                    {!trips.data.length && (
                        <p className="text-[var(--text-muted)]">Your first trip will appear here after creation.</p>
                    )}
                    {trips.data.map((trip) => (
                        <div key={trip.id} className="space-y-4 border border-[var(--border)] bg-[var(--surface)] p-5">
                            <div className="flex flex-wrap justify-between gap-4">
                                <div>
                                    <h3 className="text-xl font-bold">{trip.name}</h3>
                                    <p className="mt-1 text-sm text-[var(--text-muted)]">
                                        By {trip.creator} · {trip.participants_count} joined ·{' '}
                                        {trip.open ? 'Open' : 'Ended'} ·{' '}
                                        {trip.is_public ? 'Public leaderboard' : 'Private trip'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={busy}
                                        onClick={() => manage(trip, 'visibility', { is_public: !trip.is_public })}
                                    >
                                        {trip.is_public ? 'Hide from landing' : 'Feature on landing'}
                                    </Button>
                                    {trip.open && (
                                        <Button
                                            type="button"
                                            variant="danger"
                                            disabled={busy}
                                            onClick={() => manage(trip, 'close')}
                                        >
                                            End trip
                                        </Button>
                                    )}
                                </div>
                            </div>
                            {trip.open && (
                                <div className="flex flex-wrap items-center gap-3">
                                    <input
                                        aria-label={`Invitation link for ${trip.name}`}
                                        readOnly
                                        value={trip.invite_url}
                                        onFocus={(event) => event.target.select()}
                                        className="min-w-48 flex-1 border border-[var(--border)] bg-[var(--background)] p-2 text-sm"
                                    />
                                    <Button type="button" variant="secondary" onClick={() => copy(trip.invite_url)}>
                                        Copy link
                                    </Button>
                                    <Link href={trip.invite_url} className="text-sm font-bold text-[var(--accent)]">
                                        Join trip →
                                    </Link>
                                    <button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => manage(trip, 'rotate')}
                                        className="text-xs underline"
                                    >
                                        Replace invitation
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                    <div className="flex gap-4">
                        {trips.prev_page_url && <Link href={trips.prev_page_url}>← Previous</Link>}
                        {trips.next_page_url && <Link href={trips.next_page_url}>Next →</Link>}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
