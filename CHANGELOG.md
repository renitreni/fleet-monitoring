# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Admin Analytics — Ad Placement CSV Export**
  - `GET /admin/analytics/export` streams `ad-placement-report_<from>_<to>.csv` for the selected period (same `viewAnalytics` permission as the dashboard)
  - `AnalyticsReport::adPlacementsForRange()` ranks routes by traffic and suggests an ad format per surface: in-content + sidebar for blog, below-the-fold banner for the landing page, inline sponsored cards for route/trip lists, and explicitly ad-free product/account surfaces
  - CSV sections: ad placement opportunities (views, share of traffic %, sessions, views per session, suggested format, rationale), acquisition channels (referrer hosts), and audience geography (country share for geo-targeting)
  - "Export CSV" button on `/admin/analytics` carries the active period/custom range filters
  - Feature tests covering CSV content, authorization, and custom-range scoping

## [0.1.0] — 2026-09-03

### Added
- **Project Scaffolding**
  - Laravel 11 application bootstrapped in `car-maintenance/`
  - Docker containerization with PHP-FPM, Nginx, MySQL, Node, Redis
  - InertiaJS + React frontend integration
  - Tailwind CSS styling

- **Authentication System**
  - Email/password registration and login (Laravel Fortify)
  - Google OAuth integration (Socialite)
  - Facebook OAuth integration (Socialite)
  - Session management and logout
  - Remember me functionality

- **Car Management**
  - Full CRUD for cars with validation and ownership authorization (`CarPolicy`)
  - Car detail view with VIN, make, model, year, country, and mileage
  - Reusable `CarForm` component for create and edit flows

- **Oil Change Tracking**
  - Record last oil change date and mileage
  - Configurable intervals (time and distance)
  - Automatic next due date and mileage computation
  - Visual status indicators: OK / Due Soon / Overdue
  - Quick mileage update from car detail page

- **Oil Suggestions**
  - OpenRouter AI integration for engine oil recommendations
  - Permanent cache using SHA-256 hash deduplication per vehicle spec
  - Graceful degradation when the API is unavailable

- **Notification System**
  - Daily email notifications for overdue/due-soon oil changes
  - Notification history page with mark-as-read support
  - Anti-spam: max 1 notification per oil change per day

- **Dashboard**
  - Stats cards: total cars, overdue, due soon
  - Car grid with status badges

- **Frontend Polish**
  - Shared `EmptyState`, `LoadingSkeleton`, and `ErrorBoundary` components
  - `React.StrictMode` and error boundary wrapping the Inertia app
  - Accessibility improvements: focus-visible states, icon labels, form labels

- **Testing & Quality**
  - Feature tests for authentication, car CRUD, oil changes, oil suggestions, and notifications
  - Laravel Pint configuration and clean code style
  - ESLint flat config for React/JSX
  - Prettier configuration
  - GitHub Actions CI pipeline

## [0.2.0] — Planned

### Added
- [ ] SMS notifications via Twilio
- [ ] Push notifications via Pusher/Ably
- [ ] Multi-language support (English, Spanish, French)
- [ ] Dark mode toggle
- [ ] Export maintenance history as PDF
- [ ] Service reminder booking integration

### Changed
- [ ] Optimize OpenRouter prompts for better accuracy
- [ ] Improve dashboard loading with lazy loading

## [0.3.0] — Planned

### Added
- [ ] Mobile app (React Native)
- [ ] Barcode/VIN scanner integration
- [ ] Maintenance cost tracking
- [ ] Community forum for car maintenance tips

### Security
- [ ] Rate limiting on API endpoints
- [ ] Two-factor authentication (2FA)
- [ ] Audit logging for sensitive operations

---

## Release Notes Template

### Version X.Y.Z — YYYY-MM-DD

#### Added
- New features

#### Changed
- Changes to existing functionality

#### Deprecated
- Features marked for removal

#### Removed
- Removed features

#### Fixed
- Bug fixes

#### Security
- Security improvements

## 2026-09-08 — Group road trips

- Added a trip admin control panel with map-based route creation and ordered required checkpoints.
- Added authenticated invitations, private participant maps, explicit GPS controls, verified-progress standings and stale status.
- Added opt-in landing-page leaderboards with route creator attribution.
- Added GPS retention cleanup, an admin grant/revoke command, and automated permission/progress tests.
- Preserved invitation destinations through registration and updated documentation for the installed stack.

Activation and privacy details: [ROAD_TRIPS.md](ROAD_TRIPS.md).
