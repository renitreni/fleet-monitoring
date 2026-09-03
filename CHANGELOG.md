# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial project scaffolding and setup
- Docker containerization with PHP-FPM, Nginx, MySQL, Node, Redis
- InertiaJS + React frontend integration
- Tailwind CSS styling

- **Social Login Frontend**
  - "Continue with Google" and "Continue with Facebook" buttons on the login and register pages
  - Branded OAuth buttons with icons, "Or continue with" divider

- **Dashboard Shell**
  - Authenticated layout with navigation bar and user dropdown menu (avatar, name, email, logout)
  - Welcome dashboard page greeting the signed-in user with an overview of upcoming features
  - Logout from the user dropdown redirecting back to the login page
  - New email/password registrations are auto-verified on creation so users land directly on the dashboard

- **Cars — CRUD**
  - `CarsController` with full CRUD actions (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) rendering Inertia pages
  - `StoreCarRequest` with validation for make, model, year, current mileage, 2-letter country code, and optional VIN (used for create and update)
  - `CarPolicy` ownership authorization — users can only view/edit/delete their own cars (403 otherwise)
  - List page with empty state, "Add Car" CTA, and per-car View/Edit/Delete actions
  - Create and edit forms sharing a reusable `CarForm` component (edit fields pre-populate)
  - Car detail page with full details and edit/delete actions
  - Delete confirmation dialog before removing a car
  - Flash messages (success/error/info) rendered app-wide via the authenticated layout
  - "My Cars" navigation link in the header and user dropdown
  - Feature tests covering the full CRUD flow, validation rules, and ownership authorization

## [0.1.0] — YYYY-MM-DD

### Added
- **Authentication System**
  - Email/password registration and login (Laravel Fortify)
  - Google OAuth integration (Socialite)
  - Facebook OAuth integration (Socialite)
  - Session management and logout
  - Remember me functionality

- **Car Management**
  - Add, edit, delete cars
  - Car detail view with VIN, make, model, year
  - Country association per car
  - Authorization policies (users can only manage their own cars)

- **Oil Change Tracking**
  - Record last oil change date and mileage
  - Configurable intervals (time and distance)
  - Automatic next due date and mileage computation
  - Visual status indicators: OK / Due Soon / Overdue
  - Quick mileage update from car detail page

- **Engine Alternative Suggestions**
  - OpenRouter AI integration for engine alternatives by country
  - Smart caching using SHA-256 hash deduplication
  - Force regenerate option
  - Structured JSON response with original engine and alternatives

- **Notification System**
  - Daily email notifications for overdue oil changes
  - Notification history page
  - Mark notifications as read
  - Anti-spam: max 1 notification per oil change per day

- **Dashboard**
  - Stats cards: total cars, overdue, due soon
  - Car grid with status badges

- **Testing**
  - Feature tests for authentication (registration, login)
  - Feature tests for car CRUD operations
  - Feature tests for oil change calculations
  - Feature tests for engine suggestion caching
  - Feature tests for notification command

- **DevOps**
  - Docker Compose for local development
  - Docker Compose for production with scheduler
  - GitHub Actions CI pipeline
  - Laravel Pint code style checking
  - ESLint for JavaScript/React

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
