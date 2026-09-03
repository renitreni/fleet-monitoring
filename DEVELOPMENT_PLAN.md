# Car Maintenance Assistant — 30-Day Development Plan

## Overview

| Item | Detail |
|------|--------|
| **Project** | Car Maintenance Assistant |
| **Stack** | Laravel 11 + React 18 + InertiaJS + MySQL 8.0 |
| **Auth** | Email/password (Fortify) + Google OAuth + Facebook OAuth (Socialite) |
| **AI** | OpenRouter API for oil brand & spec suggestions |
| **Container** | Docker (PHP-FPM, Nginx, MySQL, Node, Redis) |

**Progress:** ✓ Days 1–14 complete · ◐ Days 23, 24, 26, 29 partially complete · remaining days not started

> **Legend:** `✓` = complete · `◐` = partially complete · (no icon) = not started
---

## Day-by-Day Breakdown

### Day 1 — Project Scaffolding (Part 1) ✓

**Goal:** Initialize Laravel project, Git repo, and base `.env` configuration.

**Commands:**
```bash
composer create-project laravel/laravel car-maintenance
cd car-maintenance
git init
git add .
git commit -m "Initialize Laravel 11 project"
```

**Files to create/modify:**
- `.env.example` — add all environment variable placeholders

**Testing:**
```bash
php artisan --version  # Should show Laravel 11.x
php artisan serve      # Should start without errors
```

---

### Day 2 — Project Scaffolding (Part 2) + InertiaJS Setup ✓

**Goal:** Install and configure InertiaJS with React adapter.

**Commands:**
```bash
composer require inertiajs/inertia-laravel
php artisan inertia:middleware
npm install @inertiajs/react react react-dom
npm install -D @vitejs/plugin-react tailwindcss postcss autoprefixer
npx tailwindcss init -p
```

**Files to create/modify:**
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/views/app.blade.php`
- `resources/js/app.jsx`
- `resources/js/bootstrap.js`
- `resources/css/app.css`
- `vite.config.js`
- `tailwind.config.js`

**Testing:**
```bash
npm run dev   # Vite should start without errors
```
Visit `http://localhost` — should see a blank page (no errors in console).

---

### Day 3 — Docker Containerization Setup ✓

**Goal:** Dockerize the entire application stack.

**Commands:**
```bash
# No commands needed — just create files
touch docker-compose.yml
mkdir -p docker/{app,web,node}
```

**Files to create:**
- `docker-compose.yml`
- `docker/app/Dockerfile`
- `docker/web/Dockerfile`
- `docker/web/nginx.conf`
- `docker/node/Dockerfile`
- `.dockerignore`

**Testing:**
```bash
docker-compose up -d --build
docker-compose ps          # All services should be "Up"
docker-compose exec app php artisan --version
```

---

### Day 4 — Database Design & Migrations ✓

**Goal:** Create all database tables.

**Commands:**
```bash
php artisan make:migration add_oauth_and_country_fields_to_users_table
php artisan make:migration create_cars_table
php artisan make:migration create_oil_changes_table
php artisan make:migration create_oil_suggestions_table
php artisan make:migration create_notification_logs_table
```

**Files to create/modify:**
- `database/migrations/*_add_oauth_and_country_fields_to_users_table.php`
- `database/migrations/*_create_cars_table.php`
- `database/migrations/*_create_oil_changes_table.php`
- `database/migrations/*_create_oil_suggestions_table.php`
- `database/migrations/*_create_notification_logs_table.php`

**Testing:**
```bash
docker-compose exec app php artisan migrate:fresh
# Verify all tables exist in MySQL
docker-compose exec db mysql -u root -psecret -e "SHOW TABLES;" car_maintenance
```

---

### Day 5 — Eloquent Models & Relationships ✓

**Goal:** Create all models with relationships, casts, and helper methods.

**Commands:**
```bash
php artisan make:model Car
php artisan make:model OilChange
php artisan make:model OilSuggestion
php artisan make:model NotificationLog
php artisan make:factory UserFactory --model=User
php artisan make:factory CarFactory --model=Car
php artisan make:factory OilChangeFactory --model=OilChange
```

**Files to create/modify:**
- `app/Models/User.php` — update with `country`, `provider`, `provider_id`, `SoftDeletes`
- `app/Models/Car.php`
- `app/Models/OilChange.php` — with `isDue()` and `computeNextDue()` methods
- `app/Models/OilSuggestion.php`
- `app/Models/NotificationLog.php`
- `database/factories/*.php`

**Testing:**
```bash
docker-compose exec app php artisan tinker
>>> \App\Models\User::factory()->create();
>>> \App\Models\Car::factory()->create();
```

---

### Day 6 — Authentication Backend (Part 1: Email/Password) ✓

**Goal:** Install and configure Laravel Fortify for email/password auth.

**Commands:**
```bash
composer require laravel/fortify
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
php artisan migrate
```

**Files to create/modify:**
- `config/fortify.php` — enable registration, reset passwords, email verification
- `app/Providers/FortifyServiceProvider.php`
- `app/Actions/Fortify/CreateNewUser.php` — add `country` field
- `bootstrap/providers.php` — register FortifyServiceProvider

**Testing:**
```bash
docker-compose exec app php artisan route:list | grep register
docker-compose exec app php artisan route:list | grep login
```

---

### Day 7 — Authentication Backend (Part 2: Inertia Responses) ✓

**Goal:** Configure Fortify to work with InertiaJS (redirects, shared data).

**Files to create:**
- `app/Http/Responses/LoginResponse.php`
- `app/Http/Responses/RegisterResponse.php`

**Files to modify:**
- `app/Providers/FortifyServiceProvider.php` — bind custom responses
- `routes/web.php` — add Inertia auth routes

**Routes to add:**
```php
Route::middleware(['guest'])->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
    Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
});
```

**Testing:**
```bash
curl -I http://localhost/login     # Should return 200
curl -I http://localhost/register  # Should return 200
curl -I http://localhost/dashboard # Should redirect (401/302)
```

---

### Day 8 — Authentication Frontend: Login & Register Pages ✓

**Goal:** Build React components for login and registration.

**Files to create:**
- `resources/js/Components/TextInput.jsx`
- `resources/js/Components/Label.jsx`
- `resources/js/Components/ErrorMessage.jsx`
- `resources/js/Components/Button.jsx`
- `resources/js/Pages/Auth/Login.jsx`
- `resources/js/Pages/Auth/Register.jsx`
- `resources/js/Layouts/GuestLayout.jsx`

**Testing:**
- Visit `http://localhost/register` — fill form, submit, user should be created
- Check database for user record with `country` field
- User should be redirected to `/dashboard`

---

### Day 9 — Social Login Backend (Google & Facebook) ✓

**Goal:** Install Socialite and implement OAuth for Google and Facebook.

**Commands:**
```bash
composer require laravel/socialite
```

**Files to create/modify:**
- `config/services.php` — add `google` and `facebook` configs
- `app/Http/Controllers/Auth/SocialiteController.php`
- `routes/web.php` — add OAuth routes

**Routes to add:**
```php
Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->where('provider', 'google|facebook')->name('socialite.redirect');
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])->where('provider', 'google|facebook')->name('socialite.callback');
```

**Testing:**
```bash
# Set OAuth credentials in .env first
docker-compose exec app php artisan route:list | grep auth
```

---

### Day 10 — Social Login Frontend + Dashboard Shell ✓

**Goal:** Add OAuth buttons to auth pages and build dashboard layout.

**Files to create:**
- `resources/js/Layouts/AuthenticatedLayout.jsx` — navigation bar, user dropdown, logout
- `resources/js/Pages/Dashboard.jsx`

**Files to modify:**
- `resources/js/Pages/Auth/Login.jsx` — add Google/Facebook buttons
- `resources/js/Pages/Auth/Register.jsx` — add Google/Facebook buttons

**Routes to add:**
```php
Route::post('/logout', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
```

**Testing:**
- Login page should show "Continue with Google" and "Continue with Facebook" buttons
- Dashboard should show user name and logout link
- Logout should redirect to login page

---

### Day 11 — Cars CRUD: Backend (Index, Create, Store) ✓

**Goal:** Build car management backend.

**Commands:**
```bash
php artisan make:controller CarsController
php artisan make:request StoreCarRequest
```

**Files to create/modify:**
- `app/Http/Controllers/CarsController.php`
- `app/Http/Requests/StoreCarRequest.php`
- `routes/web.php` — add `Route::resource('cars', CarsController::class)->except(['show'])`

**Validation rules (Spatie style — array notation):**
```php
public function rules(): array
{
    return [
        'make' => ['required', 'string', 'max:100'],
        'model' => ['required', 'string', 'max:100'],
        'year' => ['required', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
        'current_mileage' => ['required', 'integer', 'min:0'],
        'country' => ['required', 'string', 'size:2'],
        'vin' => ['nullable', 'string', 'max:17'],
    ];
}
```

**Testing:**
```bash
docker-compose exec app php artisan route:list | grep cars
```

---

### Day 12 — Cars CRUD: Frontend (List & Create Form) ✓

**Goal:** Build React pages for car listing and creation.

**Files to create:**
- `resources/js/Pages/Cars/Index.jsx`
- `resources/js/Pages/Cars/Create.jsx`

**Testing:**
- Visit `/cars` — should show empty state initially
- Click "Add Car" — fill form, submit
- New car should appear in list
- Verify `cars` table has the record

---

### Day 13 — Cars CRUD: Edit, Delete + Car Detail Page ✓

**Goal:** Complete car CRUD with edit, delete, and detail view.

**Commands:**
```bash
php artisan make:policy CarPolicy --model=Car
```

**Files to create/modify:**
- `app/Http/Controllers/CarsController.php` — add `edit`, `update`, `destroy`
- `app/Policies/CarPolicy.php`
- `app/Providers/AppServiceProvider.php` — register policy
- `resources/js/Pages/Cars/Edit.jsx`
- `resources/js/Pages/Cars/Show.jsx`

**Routes to update:**
```php
Route::resource('cars', CarsController::class);
```

**Testing:**
- Edit car — fields should pre-populate
- Delete car — confirmation dialog, then redirect
- Unauthorized user cannot edit/delete another's car (403)

---

### Day 14 — Oil Change Tracker: Backend ✓

**Goal:** Build oil change recording and computation system.

> **Note:** The `OilChange`/`Car` model computation logic (`computeNextDue()`, `isDue()`, `oil_status`) already exists from Day 5. The controller, form requests, and routes below are still pending.

**Commands:**
```bash
php artisan make:controller OilChangesController
php artisan make:request StoreOilChangeRequest
php artisan make:request UpdateOilChangeRequest
```

**Files to create/modify:**
- `app/Http/Controllers/OilChangesController.php`
- `app/Http/Requests/StoreOilChangeRequest.php`
- `app/Http/Requests/UpdateOilChangeRequest.php`
- `routes/web.php` — add nested routes

**Routes to add:**
```php
Route::post('cars/{car}/oil-changes', [OilChangesController::class, 'store'])
    ->name('cars.oil-changes.store');
Route::put('cars/{car}/oil-changes/{oilChange}', [OilChangesController::class, 'update'])
    ->name('cars.oil-changes.update');
```

**Testing:**
```bash
docker-compose exec app php artisan tinker
>>> $car = \App\Models\Car::first();
>>> // Test computeNextDue logic
```

---

### Day 15 — Oil Change Tracker: Frontend

**Goal:** Build oil change form and status display on car detail page.

**Files to create:**
- `resources/js/Components/OilChangeForm.jsx`
- `resources/js/Components/OilChangeStatus.jsx`

**Files to modify:**
- `resources/js/Pages/Cars/Show.jsx` — integrate oil change section

**Logic:**
- Status badge colors: green OK / yellow Due Soon / red Overdue
- "Due soon" = within 7 days of next due date

**Testing:**
- Record oil change — verify `next_due_date` and `next_due_mileage` computed correctly
- Update current mileage to exceed `next_due_mileage` — status should change to Overdue

---

### Day 16 — Mileage Update Feature

**Goal:** Allow users to update current mileage quickly from car detail page.

**Commands:**
```bash
php artisan make:controller CarMileageController
```

**Files to create/modify:**
- `app/Http/Controllers/CarMileageController.php`
- `routes/web.php` — add `cars/{car}/mileage` PUT route
- `resources/js/Components/MileageUpdate.jsx`
- `resources/js/Pages/Cars/Show.jsx` — integrate

**Validation:**
```php
'current_mileage' => ['required', 'integer', 'min:' . $car->current_mileage]
```

**Testing:**
- Try updating mileage to a lower number — should fail validation
- Update to higher number — success, oil status re-evaluates

---

### Day 17 — Email Notification System: Backend

**Goal:** Build email notification system for oil change reminders.

**Commands:**
```bash
php artisan make:notification OilChangeDueNotification
php artisan make:command CheckOilChanges
```

**Files to create/modify:**
- `app/Notifications/OilChangeDueNotification.php`
- `app/Console/Commands/CheckOilChanges.php`
- `routes/console.php` — schedule daily at 08:00

**Scheduler:**
```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('oil-changes:check')->dailyAt('08:00');
```

**Anti-spam logic:**
- Only 1 notification per oil change per day
- Check `notifications` table for existing same-day notification

**Testing:**
```bash
docker-compose exec app php artisan oil-changes:check
# Verify emails sent (check Mailpit at http://localhost:8025)
```

---

### Day 18 — Notification History + UI

**Goal:** Allow users to view and manage notification history.

**Commands:**
```bash
php artisan make:controller NotificationsController
```

**Files to create/modify:**
- `app/Http/Controllers/NotificationsController.php`
- `resources/js/Pages/Notifications/Index.jsx`
- `resources/js/Layouts/AuthenticatedLayout.jsx` — add notification bell

**Routes to add:**
```php
Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{id}/read', [NotificationsController::class, 'markAsRead'])->name('notifications.read');
```

**Testing:**
- Trigger notification command
- Visit `/notifications` — should see unread notification
- Click "Mark as read" — should update without page reload

---

### Day 19 — OpenRouter Integration: Service Class

**Goal:** Create service class for OpenRouter API communication.

**Commands:**
```bash
mkdir -p app/Services
```

**Files to create/modify:**
- `config/openrouter.php`
- `app/Services/OpenRouterService.php`

**Prompt template:**
```
Recommend the correct engine oil for a {year} {make} {model} sold in {country}.
Return JSON with: viscosity, oil_type, specification, capacity_liters, brands[], notes
```

**Error handling:**
- API key missing — log warning, return null
- API error — log error, return null
- Invalid JSON / unexpected structure — log error, return null
- All errors should be silent to user (graceful degradation)

**Testing:**
```bash
docker-compose exec app php artisan tinker
>>> $svc = new \App\Services\OpenRouterService();
>>> $svc->getOilSuggestions('Toyota', 'Corolla', 2020, 'US');
```

---

### Day 20 — Oil Suggestions: Backend

**Goal:** Build controller that recommends oil brands/specs per vehicle and caches them forever (hash-based deduplication).

**Commands:**
```bash
php artisan make:controller OilSuggestionsController
```

**Files to create/modify:**
- `app/Http/Controllers/OilSuggestionsController.php`
- `routes/web.php`

**Caching logic (cache is permanent — no force regeneration):**
```php
$sourceHash = hash('sha256', "{$make}:{$model}:{$year}:{$country}");

if (OilSuggestion::where('source_hash', $sourceHash)->exists()) {
    return redirect()
        ->route('cars.oil-suggestions.index', $car)
        ->with('success', 'Using cached oil suggestions.');
}
```

**Routes to add:**
```php
Route::get('cars/{car}/oil-suggestions', [OilSuggestionsController::class, 'index'])
    ->name('cars.oil-suggestions.index');
Route::post('cars/{car}/oil-suggestions/generate', [OilSuggestionsController::class, 'generate'])
    ->middleware('throttle:5,1')
    ->name('cars.oil-suggestions.generate');
```

**Anti-spam design:**
- Global hash deduplication by `make:model:year:country` — one AI call per unique vehicle spec
- Results are cached permanently; repeat requests (including `?force=1`) always serve the cached row
- `throttle:5,1` adds a per-user rate limit on initial generation

**Testing:**
- Generate suggestions for a car
- Generate again — should use cache (zero API calls)
- A second car with the same spec reuses the cache
- API failure is handled gracefully (error flash, no row saved)

---

### Day 21 — Oil Suggestions: Frontend

**Goal:** Build React page to display oil brand and specification suggestions with loading states.

**Files to create:**
- `resources/js/Pages/Cars/OilSuggestions.jsx`
- `resources/js/Pages/Cars/Show.jsx` — add "Oil Suggestions" link

**UI features:**
- Loading spinner while fetching from OpenRouter
- Recommended oil specification card (viscosity, oil type, specification, capacity)
- Recommended brand cards (name, notes/reason)
- Notes card
- Timestamp showing when generated
- "Cached permanently" notice (no regenerate button)

**Testing:**
- Visit `cars/{id}/oil-suggestions` — should show "No oil suggestions yet"
- Click "Get Oil Suggestions" — loading state — results
- Verify results are saved to `oil_suggestions` table

---

### Day 22 — Dashboard Enhancements

**Goal:** Build a rich dashboard with stats and car status cards.

**Files to modify:**
- `routes/web.php` — dashboard route with stats query
- `resources/js/Pages/Dashboard.jsx`

**Stats to compute:**
```php
'total_cars' => $cars->count(),
'overdue' => $cars->where('oil_status', 'overdue')->count(),
'due_soon' => $cars->where('oil_status', 'due_soon')->count(),
```

**UI:**
- 3 stat cards (Total Cars / Overdue / Due Soon)
- Car grid with status badges
- Click car to go to detail page

**Testing:**
- Dashboard loads with accurate stats
- Status badges reflect correct state

---

### Day 23 — Testing Setup & Feature Tests (Auth) ◐

**Goal:** Write PHPUnit feature tests for authentication.

**Commands:**
```bash
mkdir -p tests/Feature/Auth
```

**Files to create:**
- ✓ `tests/Feature/Auth/RegistrationTest.php`
- `tests/Feature/Auth/LoginTest.php`

**Tests to write:**
- `test_registration_screen_can_be_rendered()` — 200 + Inertia component check
- ✓ `test_new_users_can_register()` — creates user, authenticated, redirected *(implemented as `test_new_users_can_register_and_land_on_dashboard`)*
- ✓ `test_registration_requires_country()` — validation error *(implemented as `test_registration_requires_a_valid_country_code`)*
- `test_login_screen_can_be_rendered()` — 200
- `test_users_can_authenticate()` — login success
- `test_users_can_not_authenticate_with_invalid_password()` — 401

**Testing:**
```bash
docker-compose exec app php artisan test tests/Feature/Auth/
```

---

### Day 24 — Feature Tests (Cars & Oil Changes) ◐

**Goal:** Write feature tests for car CRUD and oil change calculations.

**Files to create:**
- ✓ `tests/Feature/CarsTest.php`
- ✓ `tests/Feature/OilChangesTest.php`

**Tests to write:**
- ✓ Car: index, create, update, delete, authorization (cannot modify other's car)
- ✓ OilChange: `computeNextDue()` math correctness, `isDue()` logic

**Testing:**
```bash
docker-compose exec app php artisan test
```

---

### Day 25 — Feature Tests (Suggestions & Notifications)

**Goal:** Write tests for OpenRouter integration and notification command.

**Files to create:**
- `tests/Feature/OilSuggestionsTest.php`
- `tests/Feature/NotificationCommandTest.php`

**Tests to write:**
- Oil suggestions: mock OpenRouterService, verify cache hit (1 API call), verify a second car with the same spec reuses the cache, verify force regenerate is ignored
- Notifications: assert notification sent when overdue, assert no duplicate same-day notifications

**Mocking example:**
```php
$this->mock(OpenRouterService::class, function ($mock) {
    $mock->shouldReceive('getOilSuggestions')->once()->andReturn([...]);
});
```

**Testing:**
```bash
docker-compose exec app php artisan test
```

---

### Day 26 — Docker Optimization & Production Config ◐

**Goal:** Optimize Dockerfiles for production and add scheduler service.

**Files to create/modify:**
- ✓ `docker/app/Dockerfile` — multi-stage, opcache, production optimizations
- ✓ `docker-compose.prod.yml`
- ✓ `docker/web/nginx.conf` — production headers, gzip

**Production features:**
- ✓ PHP opcache enabled
- Route/view/config caching
- Composer autoloader optimization
- ✓ Scheduler container (runs `php artisan schedule:work`)
- Health checks

**Testing:**
```bash
docker-compose -f docker-compose.prod.yml up -d --build
docker-compose -f docker-compose.prod.yml ps
```

---

### Day 27 — Code Quality & CI/CD

**Goal:** Add Laravel Pint, ESLint, and GitHub Actions workflow.

**Commands:**
```bash
composer require laravel/pint --dev
npm install -D eslint prettier
```

**Files to create:**
- `pint.json`
- `.eslintrc.cjs`
- `.github/workflows/ci.yml`
- `.prettierrc`

**CI pipeline:**
1. Checkout code
2. Setup PHP 8.2 + Node 20
3. Install composer dependencies
4. Install npm dependencies
5. Run Laravel Pint (dry-run)
6. Run ESLint
7. Copy .env, generate key
8. Run PHPUnit tests

**Testing:**
```bash
docker-compose exec app vendor/bin/pint --test
docker-compose exec node npx eslint resources/js --ext .js,.jsx
```

---

### Day 28 — Final Frontend Polish

**Goal:** Loading states, empty states, error boundaries, responsive design.

**Files to create:**
- `resources/js/Components/LoadingSkeleton.jsx`
- `resources/js/Components/EmptyState.jsx`
- `resources/js/Components/ErrorBoundary.jsx`

**Files to modify:**
- `resources/js/app.jsx` — wrap with StrictMode
- All pages — add empty states and loading handling

**Accessibility checks:**
- All form inputs have labels
- Buttons have visible focus states
- Color contrast meets WCAG AA
- All images have alt text

**Testing:**
- Run through entire app on mobile viewport
- Verify all empty states display correctly
- Verify all loading states work

---

### Day 29 — Documentation & README ◐

**Goal:** Write comprehensive documentation.

**Files to create/modify:**
- ✓ `README.md`
- ✓ `.env.example` — ensure all required keys documented

**README sections:**
1. ✓ Project description
2. ✓ Tech stack
3. ✓ Quick start (Docker)
4. ✓ Environment variables reference
5. ✓ Running tests
6. OAuth setup (Google, Facebook)
7. OpenRouter setup
8. ✓ Scheduled tasks
9. ✓ Deployment notes

---

### Day 30 — Final Review & Release Preparation

**Goal:** End-to-end testing, final changelog, version tag.

**Tasks:**
1. Complete end-to-end test checklist:
   - [ ] Register with email — verify account — login
   - [ ] Login with Google — verify account — logout — login again
   - [ ] Login with Facebook — verify account — logout — login again
   - [ ] Add a car — verify appears in list
   - [ ] Record oil change — verify next due computed
   - [ ] Update mileage — verify status updates
   - [ ] Generate suggestions — verify OpenRouter called — verify cached
   - [ ] Force regenerate — verify new API call
   - [ ] Trigger notification command — verify email sent
   - [ ] Check notification history — mark as read

2. Update `CHANGELOG.md` — finalize v0.1.0 entry

3. Tag release:
```bash
git tag -a v0.1.0 -m "Initial release - Car Maintenance Assistant"
git push origin master --tags
```

4. Final commit:
```bash
git add .
git commit -m "Prepare v0.1.0 release"
```

---

## Database Schema Reference

```
users
├── id (bigint, PK)
├── name (string)
├── email (string, unique)
├── email_verified_at (timestamp, nullable)
├── password (string)
├── country (string, 2 chars)
├── provider (string, nullable) — 'google' | 'facebook' | null
├── provider_id (string, nullable)
├── remember_token (string)
├── created_at / updated_at / deleted_at

cars
├── id (bigint, PK)
├── user_id (bigint, FK → users)
├── make (string)
├── model (string)
├── year (year)
├── current_mileage (unsigned int)
├── country (string, 2 chars)
├── vin (string, nullable)
├── created_at / updated_at

oil_changes
├── id (bigint, PK)
├── car_id (bigint, FK → cars)
├── last_changed_at (date)
├── last_changed_mileage (unsigned int)
├── interval_months (tinyint, default 6)
├── interval_mileage (int, default 5000)
├── next_due_date (date)
├── next_due_mileage (unsigned int)
├── created_at / updated_at

oil_suggestions
├── id (bigint, PK)
├── car_id (bigint, FK → cars)
├── source_hash (string, 64, unique)
├── suggestions_json (json)
│   ├── viscosity (string)
│   ├── oil_type (string)
│   ├── specification (string)
│   ├── capacity_liters (float)
│   ├── brands[] { name, reason }
│   └── notes (string)
├── created_at / updated_at

notification_logs
├── id (bigint, PK)
├── notifiable_id (bigint)
├── notifiable_type (string)
├── notification_type (string)
├── data (json, nullable)
├── sent_at (timestamp)
├── created_at / updated_at
```

---

## API Endpoints Reference

| Method | Route | Name | Auth | Description |
|--------|-------|------|------|-------------|
| GET | / | home | no | Welcome page |
| GET | /login | login | guest | Login form |
| POST | /login | — | guest | Login action |
| GET | /register | register | guest | Register form |
| POST | /register | — | guest | Register action |
| POST | /logout | logout | yes | Logout |
| GET | /auth/{provider}/redirect | socialite.redirect | guest | OAuth redirect |
| GET | /auth/{provider}/callback | socialite.callback | guest | OAuth callback |
| GET | /dashboard | dashboard | yes | Dashboard |
| GET | /cars | cars.index | yes | List cars |
| GET | /cars/create | cars.create | yes | Create car form |
| POST | /cars | cars.store | yes | Store car |
| GET | /cars/{car} | cars.show | yes | Show car |
| GET | /cars/{car}/edit | cars.edit | yes | Edit car form |
| PUT | /cars/{car} | cars.update | yes | Update car |
| DELETE | /cars/{car} | cars.destroy | yes | Delete car |
| POST | /cars/{car}/oil-changes | cars.oil-changes.store | yes | Record oil change |
| PUT | /cars/{car}/oil-changes/{oilChange} | cars.oil-changes.update | yes | Update oil change |
| PUT | /cars/{car}/mileage | cars.mileage.update | yes | Update mileage |
| GET | /cars/{car}/oil-suggestions | cars.oil-suggestions.index | yes | View oil suggestions |
| POST | /cars/{car}/oil-suggestions/generate | cars.oil-suggestions.generate | yes | Generate oil suggestions |
| GET | /notifications | notifications.index | yes | List notifications |
| POST | /notifications/{id}/read | notifications.read | yes | Mark as read |

---

## Environment Variables Reference

```env
APP_NAME="Car Maintenance Assistant"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=car_maintenance
DB_USERNAME=root
DB_PASSWORD=secret

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

OPENROUTER_API_KEY=
OPENROUTER_MODEL=openai/gpt-3.5-turbo

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost/auth/google/callback

FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI=http://localhost/auth/facebook/callback
```

---

## Command Cheat Sheet

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f app

# Run artisan commands
docker-compose exec app php artisan <command>

# Run migrations
docker-compose exec app php artisan migrate:fresh --seed

# Run tests
docker-compose exec app php artisan test

# Run code style checks
docker-compose exec app vendor/bin/pint --test

# Run scheduler manually
docker-compose exec app php artisan schedule:run

# Check oil changes command
docker-compose exec app php artisan oil-changes:check

# Build assets
docker-compose exec app npm run build

# Watch for changes
docker-compose exec node npm run dev
```
