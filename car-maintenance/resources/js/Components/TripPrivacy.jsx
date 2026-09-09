export default function TripPrivacy({ isRoute = false }) {
    return (
        <details className="border border-[var(--border)] bg-[var(--surface)] p-5 text-sm leading-6">
            <summary className="cursor-pointer font-bold">Location privacy & tracking limits</summary>
            <div className="mt-3 space-y-3 text-[var(--text-muted)]">
                <p>
                    {isRoute
                        ? 'Start attempt requests browser location access. Your live location stays private. Pause tracking removes your marker but the elapsed timer continues. Cancel attempt excludes the attempt from rankings. Leave route deletes GPS records and removes your public entry.'
                        : 'Tracking starts only when you press Start tracking and allow browser location access. Only trip participants can see your location. Stop tracking removes your map marker; Leave trip deletes your GPS records immediately and removes your public entry.'}
                </p>
                <p>
                    GPS records (coordinates, timestamp, accuracy, and optional speed) expire after 24 hours and are
                    deleted by a cleanup task every minute. Your route progress and completion record remain until the
                    route or account is deleted. Public route leaderboards show only opted-in names, best times and
                    completion dates—never GPS locations or last-seen times.
                </p>
                <p>
                    Keep this page open in the foreground. Switching apps, locking your phone, battery saving, or loss
                    of signal can pause browser tracking. Updates become stale after 30 seconds. Following a gap or
                    detour, return near your last verified route position to resume progress. Browser GPS can be
                    spoofed; this is a recreational progress board, not certified race timing.
                </p>
                <p>
                    The base map loads from OpenStreetMap. Its tile service receives your IP address and the map area
                    you view, but we do not send it participant identities or GPS updates. Use controls only when safely
                    stopped.
                </p>
            </div>
        </details>
    );
}
