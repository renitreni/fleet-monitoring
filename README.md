# Car Maintenance Assistant

A full-stack web application for car owners to track maintenance schedules and discover engine alternatives based on their country.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11 (PHP 8.2) |
| Frontend | React 18 + InertiaJS |
| Styling | Tailwind CSS |
| Database | MySQL 8.0 |
| Cache | Redis |
| AI | OpenRouter API (GPT models) |
| Auth | Laravel Fortify + Socialite |
| Mail | Mailpit (dev) / SMTP (prod) |

## Features

- **Authentication** — Email/password, Google OAuth, Facebook OAuth
- **Car Management** — Add, edit, delete cars with VIN, make, model, year
- **Oil Change Tracking** — Record changes, compute next due date/mileage
- **Notifications** — Daily email reminders when oil change is due
- **Engine Suggestions** — AI-powered alternative engines by country (cached)
- **Dashboard** — Overview of all cars with status badges

## Quick Start (Docker)

```bash
# 1. Clone the repository
git clone <repo-url>
cd car-maintenance

# 2. Copy environment file
cp .env.example .env

# 3. Start all services
docker-compose up -d --build

# 4. Install PHP dependencies
docker-compose exec app composer install

# 5. Generate app key
docker-compose exec app php artisan key:generate

# 6. Run migrations
docker-compose exec app php artisan migrate

# 7. Install JS dependencies
docker-compose exec node npm install

# 8. Build assets
docker-compose exec node npm run build
```

Access the application at **http://localhost**

### Services

| Service | URL | Purpose |
|---------|-----|---------|
| App | http://localhost | Main application |
| Mailpit | http://localhost:8025 | Email inbox (dev) |
| Vite HMR | http://localhost:5173 | Hot module replacement |

## Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_KEY` | Yes | Laravel app key (generate with `key:generate`) |
| `DB_*` | Yes | MySQL connection settings |
| `OPENROUTER_API_KEY` | Yes | API key for OpenRouter |
| `OPENROUTER_MODEL` | No | Model to use (`openai/gpt-3.5-turbo` default) |
| `GOOGLE_CLIENT_ID` | For Google auth | OAuth client ID |
| `GOOGLE_CLIENT_SECRET` | For Google auth | OAuth client secret |
| `FACEBOOK_CLIENT_ID` | For Facebook auth | OAuth app ID |
| `FACEBOOK_CLIENT_SECRET` | For Facebook auth | OAuth app secret |

## Running Tests

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test suite
docker-compose exec app php artisan test tests/Feature/Auth/
```

## Code Style

```bash
# Check PHP style
docker-compose exec app vendor/bin/pint --test

# Fix PHP style
docker-compose exec app vendor/bin/pint

# Check JS style
docker-compose exec node npx eslint resources/js --ext .js,.jsx
```

## Scheduled Tasks

The scheduler container runs `php artisan schedule:work`, which dispatches daily oil change checks at **08:00**.

To run manually:
```bash
docker-compose exec app php artisan oil-changes:check
```

## Production Deployment

```bash
# Use production compose file
docker-compose -f docker-compose.prod.yml up -d --build

# Run optimizations
docker-compose -f docker-compose.prod.yml exec app php artisan optimize
```

## Project Structure

```
├── app/
│   ├── Console/Commands/       # Artisan commands
│   ├── Http/
│   │   ├── Controllers/        # Web controllers
│   │   ├── Requests/           # Form request validation
│   │   └── Responses/          # Custom Fortify responses
│   ├── Models/                 # Eloquent models
│   ├── Notifications/          # Notification classes
│   ├── Policies/               # Authorization policies
│   ├── Services/               # Business logic
│   │   └── OpenRouterService.php
│   └── ...
├── config/openrouter.php       # OpenRouter config
├── docker/                     # Docker configuration
├── resources/js/
│   ├── Components/             # Reusable React components
│   ├── Layouts/                # Page layouts
│   └── Pages/                  # Inertia pages
├── DEVELOPMENT_PLAN.md         # 30-day development roadmap
├── CLINE_GUIDELINES.md         # Coding standards
└── CHANGELOG.md                # Release notes
```

## License

MIT
