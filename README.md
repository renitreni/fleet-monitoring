# Car Maintenance Assistant

A full-stack web application for car owners to track oil changes, monitor maintenance schedules, and get AI-powered engine oil recommendations tailored to their vehicle and country.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13 (PHP 8.4) |
| Frontend | React 19 + InertiaJS 3 |
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
- **Group road trips** — Admin route/checkpoint editor, authenticated invitations, private live GPS map, verified-progress standings, and opt-in landing-page leaderboards

See [Road trip setup, organizer guide, privacy, and staged commands](ROAD_TRIPS.md).

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
| `NVIDIA_API_KEY` | For AI blogs | NVIDIA NIM API key; never commit the populated value |
| `NVIDIA_MODEL` | No | NVIDIA model (default: `nvidia/nemotron-3.5-super-vl-120b-a12b`) |
| `NVIDIA_BASE_URL` | No | NVIDIA OpenAI-compatible base URL |
| `NVIDIA_TIMEOUT` | No | NVIDIA response timeout in seconds (default: `180`) |
| `BLOG_AI_POSTS_PER_DAY` | No | Daily generation quota (default: `10`) |
| `BLOG_AI_GENERATION_TIME` | No | Daily generation time in the blog timezone (default: `06:00`) |
| `BLOG_AI_FIRST_PUBLISH_TIME` | No | First publication slot (default: `08:00`) |
| `BLOG_TIMEZONE` | No | Blog schedule timezone (default: `Asia/Manila`) |
| `BLOG_AI_BYLINE` | No | Public byline for generated articles |
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

## NVIDIA AI Blog Automation

The scheduler runs `blog:generate-ai` every day at **06:00 Asia/Manila**. It generates up to 10 original automotive articles, stores them as scheduled posts with the `nvidia` source, and assigns publication slots from **08:00 through 17:00**. The existing `blog:publish-scheduled` task publishes due posts every minute.

At least one existing account must have blog administrator access because generated posts require an author. The public byline defaults to **Motologic Editorial Team**. API errors do not create incomplete posts; rerunning the command fills only the remaining daily quota. Posts already generated that day are counted even after publication.

1. Add `NVIDIA_API_KEY` and any optional overrides from `.env.example` to `car-maintenance/.env`.
2. Grant an existing account blog access if none exists:
   ```bash
   docker-compose exec app php artisan blog:admin owner@example.com
   ```
3. Generate one smoke-test article, publish due posts, and inspect the blog:
   ```bash
   docker-compose exec app php artisan blog:generate-ai --count=1
   docker-compose exec app php artisan blog:publish-scheduled
   ```
4. Run `blog:generate-ai` without `--count` to fill the rest of the current day's quota. Repeating it after 10 posts makes no further API calls.

Run the integration tests with fake HTTP responses (no API charges):
```bash
docker-compose exec app php artisan test --compact tests/Feature/NvidiaBlogGeneratorTest.php tests/Feature/GenerateAiBlogPostsTest.php
```

## Scheduled Tasks

The scheduler container runs `php artisan schedule:work`, which dispatches daily oil change checks at **08:00** and the blog tasks described above.

To run the oil-change check manually:
```bash
docker-compose exec app php artisan oil-changes:check
```

## Production Deployment

Production server: `ssh root@187.53.134.76` (app lives at `/opt/fleet-monitoring`, site is https://motologic.tech). Keep the real NVIDIA key only in the server's `car-maintenance/.env`; do not paste it into tracked files or shell history.

```bash
ssh root@187.53.134.76
cd /opt/fleet-monitoring

# Deploy the release containing the AI generator.
git pull origin main

# Set NVIDIA_API_KEY in car-maintenance/.env before rebuilding.
# Rebuild every PHP runtime so app, scheduler, and queue use the same release.
docker-compose -f docker-compose.prod.yml up -d --build app scheduler queue
docker-compose -f docker-compose.prod.yml exec app php artisan optimize
docker-compose -f docker-compose.prod.yml exec app php artisan schedule:list

# Verify with one real generation, then let the scheduler publish due posts.
docker-compose -f docker-compose.prod.yml exec app php artisan blog:generate-ai --count=1
docker-compose -f docker-compose.prod.yml exec app php artisan blog:publish-scheduled
docker-compose -f docker-compose.prod.yml logs --tail=100 scheduler
```

Confirm the generated article appears under https://motologic.tech/blog. If generation fails, check `storage/logs/laravel.log`, correct the configuration, and rerun the command; successful posts are not duplicated.

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
