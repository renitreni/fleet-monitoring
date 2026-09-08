import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import Label from '@/Components/Label';
import TripMap from '@/Components/TripMap';

export default function Admin({ trips, storeUrl }) {
    const [points, setPoints] = useState([]);
    const [coordinates, setCoordinates] = useState({ latitude: '14.5995', longitude: '120.9842' });
    const [notice, setNotice] = useState('');
    const [busy, setBusy] = useState(false);
    const form = useForm({ name: '', description: '', is_public: false, ends_at: '' });
    const checkpoints = points.flatMap((point, index) =>
        index === 0 || index === points.length - 1 || point.required
            ? [
                  {
                      name:
                          point.name ||
                          (index === 0 ? 'Start' : index === points.length - 1 ? 'Finish' : `Checkpoint ${index + 1}`),
                      point_index: index,
                  },
              ]
            : []
    );
    const focusPoint = { latitude: Number(coordinates.latitude), longitude: Number(coordinates.longitude) };
    const validCoordinates =
        coordinates.latitude !== '' &&
        coordinates.longitude !== '' &&
        Number.isFinite(focusPoint.latitude) &&
        Number.isFinite(focusPoint.longitude) &&
        Math.abs(focusPoint.latitude) <= 85 &&
        Math.abs(focusPoint.longitude) <= 180;
    function add(point) {
        if (points.length < 2000) setPoints((current) => [...current, { ...point, required: false, name: '' }]);
    }
    function move(index, direction) {
        setPoints((current) => {
            const copy = [...current];
            [copy[index], copy[index + direction]] = [copy[index + direction], copy[index]];
            return copy;
        });
    }
    function change(index, update) {
        setPoints((current) => current.map((point, i) => (i === index ? { ...point, ...update } : point)));
    }
    function submit(event) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            ends_at: data.ends_at ? new Date(data.ends_at).toISOString() : '',
            route_points: points.map(({ latitude, longitude }) => ({ latitude, longitude })),
            checkpoints,
        })).post(storeUrl, {
            onSuccess: () => {
                form.reset();
                setPoints([]);
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
                            <h2 className="text-xl font-black uppercase">01 / Draw the route</h2>
                            <p className="mt-2 text-sm leading-6 text-[var(--text-muted)]">
                                Zoom in and tap along the roads in travel order. Add points at every bend, 10–1,000
                                metres apart. Lines connect your points directly; there is no automatic road routing.
                            </p>
                        </div>
                        <TripMap
                            points={points}
                            checkpoints={checkpoints}
                            onAdd={add}
                            focusPoint={validCoordinates ? focusPoint : null}
                        />
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-32 flex-1">
                                <Label htmlFor="latitude">Latitude</Label>
                                <TextInput
                                    id="latitude"
                                    type="number"
                                    step="any"
                                    min="-85"
                                    max="85"
                                    value={coordinates.latitude}
                                    onChange={(event) =>
                                        setCoordinates({ ...coordinates, latitude: event.target.value })
                                    }
                                    className="border p-2"
                                />
                            </div>
                            <div className="min-w-32 flex-1">
                                <Label htmlFor="longitude">Longitude</Label>
                                <TextInput
                                    id="longitude"
                                    type="number"
                                    step="any"
                                    min="-180"
                                    max="180"
                                    value={coordinates.longitude}
                                    onChange={(event) =>
                                        setCoordinates({ ...coordinates, longitude: event.target.value })
                                    }
                                    className="border p-2"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={!validCoordinates}
                                onClick={() => add(focusPoint)}
                            >
                                Add coordinates
                            </Button>
                        </div>
                        <div className="flex items-center justify-between">
                            <h3 className="font-bold">
                                {points.length} route points · {checkpoints.length} checkpoints
                            </h3>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={!points.length}
                                onClick={() => setPoints((current) => current.slice(0, -1))}
                            >
                                Undo last point
                            </Button>
                        </div>
                        <ol className="max-h-96 space-y-2 overflow-y-auto">
                            {points.map((point, i) => (
                                <li
                                    key={i}
                                    className="flex flex-wrap items-center gap-3 border border-[var(--border)] bg-[var(--surface)] p-3"
                                >
                                    <span className="w-6 font-mono text-[var(--accent)]">{i + 1}</span>
                                    <span className="text-xs text-[var(--text-muted)]">
                                        {point.latitude.toFixed(5)}, {point.longitude.toFixed(5)}
                                    </span>
                                    <label className="flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            checked={i === 0 || i === points.length - 1 || point.required}
                                            disabled={i === 0 || i === points.length - 1}
                                            onChange={(event) => change(i, { required: event.target.checked })}
                                        />
                                        Required checkpoint
                                    </label>
                                    <input
                                        aria-label={`Name for point ${i + 1}`}
                                        value={point.name}
                                        maxLength={80}
                                        placeholder={
                                            i === 0 ? 'Start' : i === points.length - 1 ? 'Finish' : 'Checkpoint name'
                                        }
                                        onChange={(event) => change(i, { name: event.target.value })}
                                        className="min-w-28 flex-1 border border-[var(--border)] bg-[var(--background)] p-2 text-sm"
                                    />
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            aria-label={`Move point ${i + 1} earlier`}
                                            disabled={i === 0}
                                            className="px-2 disabled:opacity-30"
                                            onClick={() => move(i, -1)}
                                        >
                                            ↑
                                        </button>
                                        <button
                                            type="button"
                                            aria-label={`Move point ${i + 1} later`}
                                            disabled={i === points.length - 1}
                                            className="px-2 disabled:opacity-30"
                                            onClick={() => move(i, 1)}
                                        >
                                            ↓
                                        </button>
                                        <button
                                            type="button"
                                            aria-label={`Remove point ${i + 1}`}
                                            className="px-2 text-[var(--accent)]"
                                            onClick={() =>
                                                setPoints((current) => current.filter((_, index) => index !== i))
                                            }
                                        >
                                            ×
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ol>
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
                            Start and finish are always required. Checkpoints must be at least 100 metres apart along
                            the route. The course is fixed after creation. Review the map before saving.
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
                            disabled={points.length < 2}
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
