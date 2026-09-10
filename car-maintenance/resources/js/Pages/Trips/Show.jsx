import { useEffect, useRef, useState } from 'react';
import { router, usePoll } from '@inertiajs/react';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import TripMap from '@/Components/TripMap';
import TripLeaderboard from '@/Components/TripLeaderboard';
import TripPrivacy from '@/Components/TripPrivacy';
import RouteLeaderboard from '@/Components/RouteLeaderboard';
import RouteCompletionNotice from '@/Components/RouteCompletionNotice';
import { formatTime } from '@/lib/routePreview';

const messages = {
    verified: 'Location shared. Your route progress is verified.',
    reanchored: 'Back on the route. Progress tracking has resumed.',
    inaccurate: 'GPS accuracy is too low. Move to open sky; no progress was awarded.',
    off_route: 'Outside the verified course. Return near your last verified position to resume.',
    return_to_start: 'Go to the starting checkpoint to begin your route.',
    resume_at_progress: 'Return near your last verified position to resume after the tracking gap.',
    jump: 'GPS moved unexpectedly. Return near your last verified position.',
    detour: 'This update crosses outside the course. No progress was awarded.',
};
function stopOnExit(url, token) {
    const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));
    if (!cookie || !token) return;
    fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(cookie.slice(11)),
        },
        body: JSON.stringify({ tracking_token: token }),
    }).catch(() => {});
}

export default function Show({
    trip,
    standings,
    participant,
    urls,
    timedStandings = [],
    attempts = [],
    personalBest = null,
}) {
    const [state, setState] = useState('stopped');
    const [message, setMessage] = useState('Tracking is off. Start when you are ready at the first checkpoint.');
    const [now, setNow] = useState(Date.now());
    const [pollFailed, setPollFailed] = useState(false);
    const [showCompletion, setShowCompletion] = useState(false);
    const previouslyCompleted = useRef(participant.completed);
    const session = useRef({ generation: 0, watch: null, token: null, pending: null, lastSent: 0 });
    usePoll(
        5000,
        {
            only: ['trip', 'standings', 'participant', 'attempts', 'timedStandings', 'personalBest'],
            onSuccess: () => setPollFailed(false),
            onError: () => setPollFailed(true),
            onNetworkError: () => {
                setPollFailed(true);
                return false;
            },
            onHttpException: () => {
                setPollFailed(true);
                return false;
            },
        },
        { mode: 'rest' }
    );
    const open = trip.open && (!trip.ends_at || now < Date.parse(trip.ends_at));
    const activeAttempt = attempts.find((attempt) => ['ready', 'active'].includes(attempt.status));
    const rows = standings.map((row) => ({
        ...row,
        location: open && row.last_seen_at && now - Date.parse(row.last_seen_at) < 86400000 ? row.location : null,
        status:
            ['live', 'unverified'].includes(row.status) &&
            (!row.last_seen_at || now - Date.parse(row.last_seen_at) >= 30000)
                ? 'stale'
                : row.status,
    }));
    const own = rows.find((row) => row.id === participant.id);
    function clearLocal() {
        const current = session.current;
        current.generation++;
        if (current.watch !== null) navigator.geolocation.clearWatch(current.watch);
        current.watch = null;
        current.pending?.abort();
        current.pending = null;
    }
    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);
        const onExit = () => {
            clearLocal();
            stopOnExit(urls.stop, session.current.token);
            session.current.token = null;
        };
        const onReturn = (event) => {
            if (event.persisted) {
                setState('stopped');
                setMessage('Tracking stopped when you left this page. Press Start tracking to resume.');
            }
        };
        window.addEventListener('pageshow', onReturn);
        window.addEventListener('pagehide', onExit);
        return () => {
            clearInterval(timer);
            window.removeEventListener('pagehide', onExit);
            window.removeEventListener('pageshow', onReturn);
            onExit();
        };
    }, [urls.stop]);
    useEffect(() => {
        if (participant.completed && !previouslyCompleted.current) {
            setShowCompletion(true);
            setState('stopped');
        }
        previouslyCompleted.current = participant.completed;
        if (!open || participant.completed) {
            clearLocal();
            session.current.token = null;
        }
    }, [open, participant.completed]);
    async function stop() {
        const token = session.current.token;
        clearLocal();
        session.current.token = null;
        setState('stopped');
        setMessage('Tracking stopped on this device.');
        try {
            await axios.post(urls.stop, { tracking_token: token });
            router.reload({ only: ['standings'] });
        } catch {
            setMessage(
                'Tracking stopped on this device. Could not confirm with the server; your last marker will become stale after 30 seconds. Retry Stop tracking when connected.'
            );
        }
    }
    async function start() {
        if (!navigator.geolocation || !window.isSecureContext) {
            setMessage('Location tracking requires a supported browser over HTTPS (or localhost).');
            return;
        }
        clearLocal();
        const generation = session.current.generation;
        setState('requesting');
        setMessage(
            trip.is_route
                ? 'Allow location access to verify your attempt. Your live position stays private.'
                : 'Allow location access in your browser to share your position with this trip.'
        );
        navigator.geolocation.getCurrentPosition(
            async (first) => {
                if (generation !== session.current.generation) return;
                try {
                    const response = await axios.post(urls.start);
                    if (generation !== session.current.generation) {
                        stopOnExit(urls.stop, response.data.tracking_token);
                        return;
                    }
                    router.reload({ only: ['participant', 'attempts', 'standings'] });
                    session.current.token = response.data.tracking_token;
                    session.current.lastSent = 0;
                    setShowCompletion(false);
                    setState('tracking');
                    const upload = async (position) => {
                        if (
                            generation !== session.current.generation ||
                            session.current.pending ||
                            Date.now() - session.current.lastSent < 5000
                        )
                            return;
                        const controller = new AbortController();
                        session.current.pending = controller;
                        session.current.lastSent = Date.now();
                        const { latitude, longitude, accuracy, speed } = position.coords;
                        try {
                            const result = await axios.post(
                                urls.location,
                                {
                                    tracking_token: session.current.token,
                                    latitude,
                                    longitude,
                                    accuracy,
                                    speed: Number.isFinite(speed) && speed >= 0 ? speed : null,
                                    recorded_at: new Date(position.timestamp).toISOString(),
                                },
                                { signal: controller.signal }
                            );
                            if (generation !== session.current.generation) return;
                            setMessage(
                                result.data.completed
                                    ? 'All checkpoints verified. Trip completed and tracking stopped.'
                                    : messages[result.data.status] || 'Location updated.'
                            );
                            if (result.data.completed) {
                                setShowCompletion(true);
                                clearLocal();
                                session.current.token = null;
                                setState('stopped');
                            }
                            router.reload({
                                only: ['standings', 'participant', 'attempts', 'timedStandings', 'personalBest'],
                            });
                        } catch (error) {
                            if (generation !== session.current.generation || axios.isCancel(error)) return;
                            setMessage(
                                error.response?.data?.message ||
                                    'Connection lost. No GPS updates are queued; keep this page open and reconnect.'
                            );
                            if ([401, 403, 404, 409, 419].includes(error.response?.status)) {
                                clearLocal();
                                session.current.token = null;
                                setState('stopped');
                            }
                        } finally {
                            if (session.current.pending === controller) session.current.pending = null;
                        }
                    };
                    upload(first);
                    session.current.watch = navigator.geolocation.watchPosition(
                        upload,
                        (error) => {
                            if (generation !== session.current.generation) return;
                            setMessage(
                                error.code === 1
                                    ? 'Location permission was denied. Enable it in browser settings to try again.'
                                    : 'Waiting for a GPS signal. Keep this page open and move to open sky.'
                            );
                            if (error.code === 1) {
                                clearLocal();
                                stopOnExit(urls.stop, session.current.token);
                                session.current.token = null;
                                setState('stopped');
                            }
                        },
                        { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 }
                    );
                } catch (error) {
                    if (generation === session.current.generation) {
                        setState('stopped');
                        setMessage(
                            error.response?.data?.message ||
                                'Could not start tracking. Check your connection and try again.'
                        );
                    }
                }
            },
            (error) => {
                if (generation !== session.current.generation) return;
                setState('stopped');
                setMessage(
                    error.code === 1
                        ? 'Location permission was denied. Enable location access in browser settings, then try again.'
                        : 'Could not get your location. Move to open sky and try again.'
                );
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 }
        );
    }
    function leave() {
        if (
            !window.confirm('Leave this trip and delete your location records? Your public entry will also be removed.')
        )
            return;
        clearLocal();
        stopOnExit(urls.stop, session.current.token);
        session.current.token = null;
        setState('stopped');
        router.post(urls.leave);
    }
    async function cancelAttempt() {
        clearLocal();
        session.current.token = null;
        setState('stopped');
        try {
            await axios.post(urls.cancel);
            setMessage('Attempt cancelled. Start again when you are ready at the start point.');
            router.reload({ only: ['participant', 'attempts', 'standings'] });
        } catch {
            setMessage('Could not cancel the attempt. Retry when connected.');
        }
    }
    return (
        <AuthenticatedLayout
            title={trip.name}
            header={
                <div>
                    <p className="eyebrow">{trip.is_route ? 'Your route attempt' : 'Legacy road trip'}</p>
                    <h1 className="mt-2 text-3xl font-black uppercase">{trip.name}</h1>
                    <p className="mt-3 text-sm text-[var(--text-muted)]">
                        Route by {trip.creator} · {(trip.distance_m / 1000).toFixed(1)} km
                        {trip.ends_at ? ` · Ends ${new Date(trip.ends_at).toLocaleString()}` : ' · Join anytime'}
                    </p>
                </div>
            }
        >
            {showCompletion && <RouteCompletionNotice />}
            <div className="mx-auto max-w-[1384px] space-y-7 px-5 py-8 sm:px-8">
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        type="button"
                        onClick={start}
                        disabled={!open || (!trip.is_route && participant.completed) || state !== 'stopped'}
                    >
                        {state === 'requesting'
                            ? 'Requesting location…'
                            : state === 'tracking' && open && !participant.completed
                              ? 'Tracking active'
                              : trip.is_route
                                ? activeAttempt
                                    ? 'Resume attempt'
                                    : 'Start attempt'
                                : 'Start tracking'}
                    </Button>
                    <Button type="button" variant="secondary" onClick={stop}>
                        {trip.is_route ? 'Pause tracking' : 'Stop tracking'}
                    </Button>
                    {trip.is_route && activeAttempt && (
                        <Button type="button" variant="danger" onClick={cancelAttempt}>
                            Cancel attempt
                        </Button>
                    )}
                    <Button type="button" variant="danger" className="ml-auto" onClick={leave}>
                        Leave route
                    </Button>
                </div>
                <p
                    role="status"
                    aria-live="polite"
                    className="border-l-2 border-[var(--accent)] bg-[var(--surface)] p-4 text-sm leading-6"
                >
                    {!open
                        ? 'This route is closed. Location sharing is off.'
                        : participant.completed
                          ? trip.is_route
                              ? 'Course completed. Your result is saved. You can start another attempt.'
                              : 'All required checkpoints completed. Location sharing is off.'
                          : message}
                </p>
                <p className="text-sm text-[var(--text-muted)]">
                    Keep this page open in the foreground. Locking your phone or switching apps may pause tracking.
                    Updates are marked stale after 30 seconds.
                </p>
                {trip.is_route && (
                    <section className="grid gap-4 sm:grid-cols-2">
                        <div className="border border-[var(--border)] p-5">
                            <p className="eyebrow">Current attempt</p>
                            <p className="mt-2 font-mono text-2xl font-bold">
                                {activeAttempt?.started_at
                                    ? formatTime(Math.max(0, now - Date.parse(activeAttempt.started_at)))
                                    : activeAttempt
                                      ? 'Waiting for start GPS'
                                      : 'Ready when you are'}
                            </p>
                            <p className="mt-2 text-xs text-[var(--text-muted)]">
                                Elapsed time includes pauses and stops.
                            </p>
                        </div>
                        <div className="border border-[var(--border)] p-5">
                            <p className="eyebrow">Personal best</p>
                            <p className="mt-2 font-mono text-2xl font-bold">{formatTime(personalBest)}</p>
                        </div>
                    </section>
                )}
                {pollFailed && <p role="alert">Could not refresh the group. Last known positions may be stale.</p>}
                <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
                    <TripMap
                        points={trip.route_points}
                        checkpoints={trip.checkpoints}
                        participants={rows}
                        focusPoint={participant.resume_point}
                        resumePoint={participant.resume_point}
                        focusLabel="Your resume point"
                    />
                    <aside className="space-y-5">
                        <h2 className="text-lg font-black uppercase">Your checkpoints</h2>
                        <p className="text-sm text-[var(--text-muted)]">{trip.description}</p>
                        <ol className="space-y-3">
                            {trip.checkpoints.map((checkpoint, i) => (
                                <li key={i} className="flex items-center gap-3 border border-[var(--border)] p-3">
                                    <span className="grid h-8 w-8 shrink-0 place-items-center bg-[var(--surface-muted)] font-mono">
                                        {i < (own?.checkpoints_completed || 0) ? '✓' : i + 1}
                                    </span>
                                    <div>
                                        <p className="text-sm font-bold">{checkpoint.name}</p>
                                        <p className="text-xs text-[var(--text-muted)]">
                                            {(checkpoint.distance_m / 1000).toFixed(1)} km
                                            {i === (own?.checkpoints_completed || 0) ? ' · Up next' : ''}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </aside>
                </div>
                <section className="space-y-4">
                    <div>
                        <p className="eyebrow">Every checkpoint counts</p>
                        <h2 className="mt-2 text-2xl font-black uppercase">
                            {trip.is_route ? 'Route leaderboard' : 'Trip leaderboard'}
                        </h2>
                        <p className="mt-2 text-sm text-[var(--text-muted)]">
                            {trip.is_route
                                ? 'Best verified completion time per driver. Equal times share a rank. Only shared results appear.'
                                : 'Verified route progress, then checkpoints. Equal scores share a rank.'}
                        </p>
                    </div>
                    {trip.is_route ? (
                        <RouteLeaderboard rows={timedStandings} />
                    ) : (
                        <TripLeaderboard rows={rows} checkpointCount={trip.checkpoints.length} />
                    )}
                </section>
                {trip.is_route && (
                    <section className="space-y-4">
                        <h2 className="text-2xl font-black uppercase">Your recent attempts</h2>
                        <p className="text-sm text-[var(--text-muted)]">
                            Your latest 20 attempts. Your personal best includes all completed attempts.
                        </p>
                        {!attempts.length && <p>No attempts yet. Start when you reach the first checkpoint.</p>}
                        <ol className="divide-y divide-[var(--border)]">
                            {attempts.map((attempt) => (
                                <li key={attempt.id} className="flex flex-wrap justify-between gap-3 py-4 text-sm">
                                    <span className="capitalize">
                                        {attempt.status === 'ready' ? 'Waiting for start' : attempt.status} ·{' '}
                                        {attempt.started_at
                                            ? new Date(attempt.started_at).toLocaleString()
                                            : 'Not started'}
                                    </span>
                                    <span className="font-mono">
                                        {attempt.elapsed_ms ? formatTime(attempt.elapsed_ms) : '—'}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </section>
                )}
                <label className="flex items-start gap-3 border border-[var(--border)] p-5 text-sm">
                    <input
                        className="mt-1"
                        type="checkbox"
                        checked={participant.public_consent}
                        onChange={(event) =>
                            router.patch(
                                urls.consent,
                                { public_consent: event.target.checked },
                                { preserveScroll: true }
                            )
                        }
                    />
                    <span>
                        {trip.is_route
                            ? 'Show my name, best time, and completion date on the public route leaderboard. My live location stays private.'
                            : 'Show my name and progress on the public trip leaderboard.'}
                    </span>
                </label>
                <TripPrivacy isRoute={trip.is_route} />
            </div>
        </AuthenticatedLayout>
    );
}
