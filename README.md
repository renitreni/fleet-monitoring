# Car Maintenance Assistant

A full-stack web application for car owners to track oil changes, monitor maintenance schedules, and get AI-powered engine oil recommendations tailored to their vehicle and country.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11 (PHP 8.3) |
| Frontend | React 19 + InertiaJS |
| Styling | Tailwind CSS 4 |
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
- **Oil Suggestions** — AI-powered engine oil recommendations (cached forever)
- **Dashboard** — Overview of all cars with status badges

## Quick Start (Docker)

The application is containerized with Docker. The Laravel application lives in the `car-maintenance/` directory.

```bash
# 1. Clone the repository
git clone <repo-url>
cd fleet-monitoring

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

# 8. Build assets (or use `npm run dev` for HMR)
docker-compose exec node npm run build
```

Access the application at **http://localhost:8080**

### Services

| Service | URL | Purpose |
|---------|-----|---------|
| App | http://localhost:8080 | Main application |
| Mailpit | http://localhost:8026 | Email inbox (dev) |
| Vite HMR | http://localhost:5173 | Hot module replacement |

## Environment Variables Reference

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_KEY` | Yes | Laravel app key (generate with `key:generate`) |
| `DB_*` | Yes | MySQL connection settings |
| `OPENROUTER_API_KEY` | For AI suggestions | API key for OpenRouter |
| `OPENROUTER_MODEL` | No | Model slug (default: `openai/gpt-3.5-turbo`) |
| `OPENROUTER_BASE_URL` | No | OpenRouter API base URL |
| `OPENROUTER_TIMEOUT` | No | Request timeout in seconds (default: `30`) |
| `GOOGLE_CLIENT_ID` | For Google auth | OAuth client ID |
| `GOOGLE_CLIENT_SECRET` | For Google auth | OAuth client secret |
| `GOOGLE_REDIRECT_URI` | For Google auth | Must match the redirect URI in Google Cloud Console |
| `FACEBOOK_CLIENT_ID` | For Facebook auth | OAuth app ID |
| `FACEBOOK_CLIENT_SECRET` | For Facebook auth | OAuth app secret |
| `FACEBOOK_REDIRECT_URI` | For Facebook auth | Must match the redirect URI in Facebook Developers |

## OAuth Setup

### Google

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project or select an existing one.
3. Navigate to **APIs & Services > Credentials**.
4. Click **Create Credentials > OAuth client ID**.
5. Configure the consent screen if prompted.
6. For **Application type**, choose **Web application**.
7. Add the following **Authorized redirect URI**:
   - `http://localhost:8080/auth/google/callback`
8. Copy the **Client ID** and **Client Secret** into your `.env` file:
   ```env
   GOOGLE_CLIENT_ID=your-client-id
   GOOGLE_CLIENT_SECRET=your-client-secret
   GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback
   ```

### Facebook

1. Go to the [Facebook Developers portal](https://developers.facebook.com/).
2. Create a new app (use **Other** > **Consumer** or **None** type).
3. Add the **Facebook Login** product to your app.
4. Navigate to **Settings > Basic** to find the **App ID** and **App Secret**.
5. Go to **Facebook Login > Settings** and add the following **Valid OAuth Redirect URI**:
   - `http://localhost:8080/auth/facebook/callback`
6. Copy the credentials into your `.env` file:
   ```env
   FACEBOOK_CLIENT_ID=your-app-id
   FACEBOOK_CLIENT_SECRET=your-app-secret
   FACEBOOK_REDIRECT_URI=http://localhost:8080/auth/facebook/callback
   ```

## OpenRouter Setup

Oil suggestions are generated through the [OpenRouter](https://openrouter.ai/) API.

1. Create an account at [https://openrouter.ai](https://openrouter.ai/).
2. Go to **Keys** and create a new API key.
3. Add the key to your `.env` file:
   ```env
   OPENROUTER_API_KEY=sk-or-v1-...
   ```
4. Optionally change the model:
   ```env
   OPENROUTER_MODEL=openai/gpt-3.5-turbo
   ```

> **Note:** Suggestions are cached permanently per vehicle specification (`make:model:year:country`). The AI is called at most once for each unique combination.

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
docker-compose exec node npm run lint

# Fix JS style
docker-compose exec node npm run lint:fix

# Format JS with Prettier
docker-compose exec node npm run format
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

## CI/CD

A GitHub Actions workflow is located at `car-maintenance/.github/workflows/ci.yml`. It runs Laravel Pint, ESLint, builds the frontend, and executes the PHPUnit test suite on every push and pull request to `master`/`main`.

## Project Structure

```
├── car-maintenance/            # Laravel + React application
│   ├── app/                    # Controllers, models, services
│   ├── config/openrouter.php   # OpenRouter config
│   ├── resources/js/           # React components and pages
│   ├── tests/                  # Feature and unit tests
│   └── .github/workflows/      # CI/CD workflows
├── docker/                     # Docker configuration
├── docker-compose.yml          # Local development stack
├── docker-compose.prod.yml     # Production stack
├── DEVELOPMENT_PLAN.md         # 30-day development roadmap
├── CLINE_GUIDELINES.md         # Coding standards
└── CHANGELOG.md                # Release notes
```

## License

MIT
