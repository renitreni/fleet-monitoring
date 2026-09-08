# Motologic group road trips — MVP

Motologic now includes an admin trip control panel, invitation-based group trips, foreground browser GPS tracking, private group maps, and optional landing-page leaderboards. Existing car, oil-change, reminder, recommendation, and authentication features remain in place.

## Stack and implementation stages

The installed application uses PHP 8.4, Laravel 13, Fortify 1, Socialite 5, Inertia Laravel 3 / React adapter 3, React 19, and Tailwind 4. Production uses MySQL; automated feature tests use isolated SQLite. No package dependencies were added. The map uses OpenStreetMap raster tiles with a React/SVG overlay and standard browser caching.

| Stage | Delivered work | Validation |
| --- | --- | --- |
| 1 — Foundation | Additive tables, protected admin flag, invitation and membership rules, tracking sessions, GPS retention | Permission, invitation, and registration tests |
| 2 — Admin and join experience | Route drawing and coordinate entry, ordered checkpoints, invitation management, trip list and join screen | Request validation and Inertia page tests; frontend build |
| 3 — Tracking and standings | Server-side route projection, checkpoint ordering, stale status, private live map, Start/Stop/Leave | GPS, detour, corner-cutting, completion, session, and retention tests |
| 4 — Public presentation | Creator attribution, opt-in public standings, shared styling, setup documentation | Public response allowlist, consent, branding and maintenance regressions |

All four stages are implemented in this change. The following checkpoints can be run on separate days if convenient; further AI commands are not required to finish the MVP. Deploy the migration and application/assets together, rather than exposing partially activated code to users.

## Activation and optional daily commands

Run local commands from `car-maintenance/`, with the application's existing PHP and Node dependencies installed.

### Checkpoint A — Validate without changing application data

```bash
cd car-maintenance
php artisan test --compact tests/Feature/TripsTest.php tests/Feature/TripTrackingTest.php
npm run lint
npm run build
```

Tests migrate only their isolated in-memory test database. The build creates the assets; it does not deploy the application.

### Checkpoint B — Activate on your chosen environment

Apply the normal backup/deployment process first. The additive migration creates three trip tables and adds `users.is_trip_admin` with a default of false. It does not modify maintenance records. The landing page queries the new trip tables, so migrate before serving the updated application.

```bash
# From car-maintenance/
php artisan migrate
npm run build
php artisan optimize:clear
php artisan trips:admin organizer@example.com
```

Replace `organizer@example.com` with an existing registered account. This grants trip administration only. Do not put `is_trip_admin` in a registration form or the User model's fillable attributes. Revoke it with:

```bash
php artisan trips:admin organizer@example.com --revoke
```

For the repository's local Docker environment, run from the repository root:

```bash
docker compose exec app php artisan migrate
docker compose exec node npm run build
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan trips:admin organizer@example.com
```

Use your existing production image deployment process for production; the production compose configuration bakes application code into the image. Ensure PHP, web assets, and the scheduler all run the same release. Do not use `migrate:fresh` on application data. Rolling this migration back deletes trip data, so prefer a forward fix after launch.

### Checkpoint C — Confirm scheduler and regression checks

The existing scheduler also runs `trips:prune-locations` every minute. It must be running wherever tracking is enabled. The existing Docker scheduler uses `schedule:work`; do not start a duplicate scheduler. For local non-Docker development, run `php artisan schedule:work` in a separate terminal.

```bash
# From car-maintenance/
php artisan schedule:list
php artisan trips:prune-locations
php artisan test --compact
```

The prune command deletes expired GPS records; it is idempotent. Never enable location collection on an environment without an operating cleanup scheduler. Serve the app over HTTPS; localhost is the browser's development exception.

## Organizer guide

1. Sign in to the account granted trip admin access. Open **Trip admin** in the navigation (`/admin/trips`).
2. Pan/zoom the map or enter latitude/longitude and select **Go to coordinates**. Tap the map or select **Add coordinates** to add route points in travel order.
3. Follow roads accurately, adding a point at every bend. Segments are straight lines between points, not automatically calculated driving directions. Points must be 10–1,000 metres apart. Routes must be 100 metres–500 kilometres and contain 2–2,000 points. The map begins in Manila for convenience and can be moved elsewhere.
4. Mark intermediate route points as **Required checkpoint** and name them. Start and finish are always required. Required checkpoints must be in strictly increasing route order, at least 100 metres apart along the course, with no duplicates. Up/down and remove controls change the route order before saving. The maximum is 50 required checkpoints.
5. Add a trip name, meet-up details, and an end time within the next 30 days. The input uses the organizer's local time; the browser submits an ISO timestamp.
6. Optionally feature the trip on the landing page. This publishes the route name, distance, checkpoint count, and your account name as creator. Participant entries require each person's separate opt-in.
7. Select **Create trip & invitation**, then **Copy link**. Course geometry and checkpoints are immutable after creation to keep standings comparable. Create a new trip if the course needs correction.
8. Use **Replace invitation** to revoke an existing link, **Hide from landing** to remove public presentation, or **End trip** to stop all tracking and joining. Ending a trip is irreversible in this MVP.

Admins manage trip metadata, but must join through an invitation to view live trip locations. Any current trip admin can manage trips. Grant the role only to trusted organizers. No default or automatic admin account is created.

## Participant guide

- Open the organizer's invitation. Guests are redirected to login; registration and existing email/password or social login return to the invitation. Viewing a link does not join or start tracking.
- Review the trip and select **Join trip**. Public leaderboard consent defaults to off and can be withdrawn on the trip page.
- At the starting checkpoint, select **Start tracking** and allow browser location access. No GPS request occurs just because a trip page is opened. If permission is denied, enable it in browser settings and try again.
- The app submits fresh GPS updates at most once every five seconds and refreshes the group view approximately every five seconds while foregrounded. It never queues offline GPS history for later upload.
- Follow required checkpoints in order. After a GPS gap or detour, use **Your resume point** to locate the blue dashed marker showing the last verified point on the planned route. Return near it before continuing. Route points and numbered checkpoint markers remain visible even if map tiles fail.
- **Stop tracking** clears the browser watch, invalidates the tracking session on the server, and removes the participant's current marker. Leaving the page also attempts a best-effort stop; abrupt process termination cannot guarantee delivery.
- **Leave trip** immediately deletes that membership's GPS records, stops tracking, removes its public consent and leaderboard entry, and revokes trip-location access. A valid invitation permits rejoining with saved aggregate progress; location sharing must be started again. Only one membership exists per account/trip.
- Completion occurs only after all required checkpoints have been verified in order. Completion stops tracking automatically.

## Ranking and GPS verification contract

“Route challenge” is the user-facing name for this MVP's requested time-attack concept. It deliberately uses progress rankings, not competitive elapsed times, fastest arrival, or top speed. There is no timed racing mode.

The server owns distance, progress, checkpoint counters, completion timestamps, and tracking tokens. Client-supplied score fields are ignored. Each accepted GPS payload records latitude, longitude, capture timestamp, accuracy in metres, optional speed in metres/second, server receipt time, and whether it awarded verified progress.

- Coordinates must be numeric within the map's supported bounds (latitude ±85°, longitude ±180°); antimeridian-crossing segments are not supported.
- Captures older than 30 seconds, more than 5 seconds in the future, duplicate captures, and out-of-order captures return validation errors and are not stored. Synchronize device clocks.
- Accuracy worse than 30 metres cannot earn progress or provide a map marker. The position's accuracy circle must fit inside a 40-metre route corridor. Inaccurate fresh fixes may be stored as unverified diagnostics for the same 24-hour retention period.
- A starting fix must fit within the 40-metre starting checkpoint radius. It awards the first checkpoint but no unobserved distance.
- Progress projects onto a contiguous reachable section of the planned polyline. It does not accumulate total distance traveled; traveling backwards or off-course cannot add detour distance.
- Continuous fixes must arrive with capture gaps no greater than 30 seconds. Forward projection is bounded to 200 metres per update and a plausibility allowance of `55 × elapsed_seconds + 10` metres; this is a GPS jump filter, not a scored speed or a suggested driving speed.
- The observed line between fixes is sampled against the course corridor to reject corner cutting. Off-route, inaccurate, implausible, and gap updates break continuity.
- After a gap, detour, or restart, a fix must project within 20 metres of the current verified progress to re-anchor. Re-anchoring itself awards no progress. This intentionally favors missing credit over credit for an unobserved shortcut.
- Progress is capped at the next required checkpoint. The capture must fit in that checkpoint's 40-metre radius and be at its position along the route; passing later checkpoints cannot complete a skipped checkpoint.
- Standings sort by whole verified metres, then required checkpoints completed. Equal scores share a rank (public ranks are among opted-in entries); account entry ID only stabilizes display order among ties. Every completed participant has the full route score. Speed and completion time are absent from the ordering.

GPS is approximate. Detours inside the corridor and journeys between sparse samples cannot be perfectly distinguished from following the course. Browser location can be spoofed. This MVP provides conservative recreational verification, not tamper-proof telemetry or certified competition timing. Complex overlapping routes and device-specific location behavior need field testing before a large event.

## Privacy and retention

| Data | Visibility | Retention |
| --- | --- | --- |
| Planned route and checkpoints | Current participants; organizer while creating | Until the trip is deleted from storage |
| Raw captures, including optional speed | Server storage; no raw-history endpoint | Expire 24 hours after receipt; deletion on next minutely cleanup |
| Current GPS marker | Current participants only, including joined admins | Hidden on Stop, completion, trip end, or expiry; deleted on Leave |
| Cached last/verified GPS fix | Server-side verification; current marker allowlist only | Same 24-hour window; also cleared on Stop/Leave/end |
| Progress, checkpoint totals, completion timestamp | Current participants; selected summary fields publicly only with consent | Until the trip/membership is deleted from storage |
| Public route summary | Everyone, if the admin features it | Until hidden or trip deletion |
| Public participant entry | Account name, initial avatar, progress, checkpoint totals, completion boolean | Until consent withdrawn, Leave, trip hidden, or deletion |

The public response contains no coordinates, route geometry, raw GPS, speed, capture time, last-seen time, connection status, email, invitation token, or tracking token. Account avatars use initials, matching the existing navigation; no external avatar service receives user information.

Authenticated trip and admin responses use `Cache-Control: private, no-store`. Trip history is encrypted with Inertia; Leave clears the history encryption key. HTTPS, session authentication, CSRF protection, per-action authorization, unguessable 64-character invitations, row locking, and request throttles protect trip operations. Tracking tokens change on each Start and become unusable on Stop/Leave/completion. A late stop request from an older browser session cannot stop a newer token's session.

The 24-hour cleanup applies to application storage, not independent infrastructure backups. Operators must exclude raw GPS from analytics and request-body logs and configure backup retention/restoration so expired location data is not reintroduced. The application must not advertise a shorter infrastructure retention period than its operator actually provides.

OpenStreetMap receives normal tile requests (viewer IP, browser metadata, origin referrer, and tile area). Participant identities and GPS payloads are sent only to Motologic. The app displays OpenStreetMap attribution, uses browser HTTP caching, and does not prefetch or offer offline map downloads. Tile availability is best effort; choose an appropriate hosted/self-hosted tile service before high-volume use, following [OSM's tile policy](https://operations.osmfoundation.org/policies/tiles/).

## Connection status and browser limitations

- **Live:** a recent, usable route update.
- **Unverified:** recent GPS updates are arriving but cannot verify route progress.
- **Stale:** no fresh receipt for 30 seconds, including network loss or suspended browser execution. The client also ages the indicator if polling fails. A stale marker is a last known position, not a current position.
- **Stopped / completed / ended:** location sharing is off.

A browser cannot guarantee continuous background GPS. Switching apps, locking a phone, power-saving modes, lost signal, or closing a tab can suspend callbacks and polling. No service worker or background tracking claim is made. See [MDN's watchPosition documentation](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/watchPosition) and [Inertia polling behavior](https://inertiajs.com/docs/v3/data-props/polling). Use controls while safely stopped.

## Validation and future work

Automated tests cover admin permission and privilege injection, membership isolation (including admins and departed members), invitation rotation/expiration and auth return paths, immutable routes, checkpoint validation and skipping, GPS age/order/accuracy, detours and corner cuts, stop/session races, consent, retention, ranking ties, and existing maintenance behavior. Frontend validation includes ESLint and a production Vite build. The landing-page branding test now initializes its test database because the page includes real leaderboard data.

No visual QA on live pages was performed, per repository instructions. No real device GPS/background tests or production MySQL concurrency/load tests were performed. Before a group event, test two participant accounts and an outsider on HTTPS, permission denial/retry, Stop and Leave, locking/unlocking a phone, lost connectivity, and returning to the resume marker. This is functional field testing, not a promise that browsers track in the background.

Potential follow-ups: automated road routing/geocoding, editable drafts before publication, configurable map provider, route templates, actual uploaded avatars, websocket delivery for larger groups, native background location, anti-spoofing telemetry, and event-scale performance work. They are outside this MVP and do not replace the delivered browser feature.
