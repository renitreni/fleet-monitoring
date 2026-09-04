# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_No unreleased changes yet._

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
