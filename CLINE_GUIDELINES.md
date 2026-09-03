# Cline Guidelines — Car Maintenance Assistant

> These guidelines are derived from Spatie's published coding guidelines at https://github.com/spatie/guidelines.spatie.be and adapted for this project's tech stack: Laravel 11 + React 18 + InertiaJS + MySQL.

Apply these guidelines to every code generation request in this project.

---

## Table of Contents

- [PHP / Laravel Guidelines](#php--laravel-guidelines)
- [JavaScript / React Guidelines](#javascript--react-guidelines)
- [Database & Eloquent Guidelines](#database--eloquent-guidelines)
- [Testing Guidelines](#testing-guidelines)
- [Git & Version Control](#git--version-control)

---

## PHP / Laravel Guidelines

### Code Style

- Follow PSR-1, PSR-2, and PSR-12.
- Use 4 spaces for indentation (never tabs).
- All non-public-facing strings: camelCase.
- Public-facing strings (URLs, route names): kebab-case.
- Class names: PascalCase.
- Constants: UPPER_SNAKE_CASE.

### Typed Properties

Always type properties. Never use docblocks for typed properties.

```php
// Good
class Car
{
    public string $make;
    public int $year;
    public ?string $vin = null;
}

// Bad
class Car
{
    /** @var string */
    public $make;
}
```

### Docblocks

Don't use docblocks for methods that can be fully type-hinted. Add descriptions only when they provide more context than the signature itself.

```php
// Good
public static function fromString(string $url): Url
{
    // ...
}

// Bad — redundant
/**
 * Create a url from a string.
 *
 * @param string $url
 * @return App\Models\Url
 */
public static function fromString(string $url): Url
{
    // ...
}
```

When docblocks are needed, use fully qualified class names.

```php
// Good
/** @var App\Models\Car */
private $car;

// Bad
/** @var Car */
private $car;
```

Keep docblocks on one line when possible.

### Void Return Types

If a method returns nothing, declare void:

```php
public function computeNextDue(): void
{
    $this->next_due_date = $this->last_changed_at->copy()->addMonths($this->interval_months);
}
```

### Strings

Always use single quotes unless interpolating variables.

```php
// Good
$greeting = 'Hello world';
$message = "Welcome, {$name}";

// Bad
$greeting = "Hello world";
```

Prefer sprintf for complex string construction.

### If Statements

Always use curly braces. Ternary for simple assignments only.

```php
// Good
if ($car->oilChange->isDue()) {
    $this->sendNotification($car);
}

// Good ternary
$result = $condition ? 'yes' : 'no';

// Bad
if ($car->oilChange->isDue())
    $this->sendNotification($car);
```

### Configuration

Use config() helper, never env() outside of config files.

```php
// Good
$apiKey = config('openrouter.api_key');

// Bad
$apiKey = env('OPENROUTER_API_KEY');
```

### Routing

Route names: kebab-case.

```php
// Good
Route::get('/oil-changes/check', [OilChangesController::class, 'check'])->name('oil-changes.check');

// Bad
Route::get('/oilChanges/check', ...)->name('oilChanges.check');
```

### Controllers

- Controllers should be thin. Move business logic to service classes or models.
- Return Inertia responses or redirects. Never return JSON directly from web controllers.

```php
// Good
public function index(Request $request): Response
{
    $cars = $request->user()->cars()->latest()->get();
    return Inertia::render('Cars/Index', ['cars' => $cars]);
}
```

### Validation

Use Form Request classes for validation. Use array notation for rules (never pipe-delimited).

```php
// Good
public function rules(): array
{
    return [
        'email' => ['required', 'email'],
        'make' => ['required', 'string', 'max:100'],
    ];
}

// Bad
public function rules(): array
{
    return [
        'email' => 'required|email',
    ];
}
```

Custom validation rules: snake_case.

```php
Validator::extend('organisation_type', function ($attribute, $value) {
    return OrganisationType::isValid($value);
});
```

### Authorization

Policy methods: camelCase. Default to CRUD words, replace show with view.

```php
// Good
Gate::define('editPost', fn ($user, $post) => $user->id === $post->user_id);
Gate::define('viewPost', fn ($user, $post) => true);
```

### Naming Classes

| Type | Convention | Example |
|------|-----------|---------|
| Controllers | Plural resource + Controller | CarsController |
| Form Requests | Action + Resource | StoreCarRequest |
| Jobs | Action description | SendOilChangeReminder |
| Events | Past tense for after-action | OilChangeRecorded |
| Listeners | Action + Listener | SendNotificationListener |
| Commands | Action + Command | CheckOilChangesCommand |
| Mail | Subject + Mail | OilChangeDueMail |
| Services | Noun describing domain | OpenRouterService |

### Artisan Commands

- Use $signature for command name.
- Return Command::SUCCESS or Command::FAILURE.
- Log output with $this->info(), $this->warn(), $this->error().

### Translations

Use __() helper:

```php
<h2>{{ __('car.form.title') }}</h2>
{!! __('car.form.description') !!}
```

### Blade Templates

- Indent with 4 spaces.
- No spaces after control structures.

```blade
@if($condition)
    Something
@endif
```

---

## JavaScript / React Guidelines

### Code Style

- 4 spaces indentation.
- Single quotes for strings (or backticks for interpolation).
- Semicolons: required.
- Line length: aim for ~120 characters.

### Spacing

```javascript
// Good
if (true) {
    // ...
} else {
    // ...
}

const two = 1 + 1;

// Bad
if(true){
    // ...
}else{
    // ...
}

const two = 1+1;
```

### Functions

Named functions: no space before parentheses.
Anonymous functions: space before parentheses.
Arrow functions for callbacks; named functions for declarations.

```javascript
// Good — named function
function save(user) {
    return user.save();
}

// Good — anonymous callback with space
save(user, function (response) {
    console.log(response);
});

// Good — arrow function for simple callback
['a', 'b'].map(a => a.toUpperCase());
```

### Arrow Functions

Omit parentheses for single-parameter one-liners. Use parentheses when body has braces.

```javascript
// Good
items.map(item => item.name);

// Good
const saveUser = (user) => {
    // multi-line
};

// Bad
const saveUser = user => {
    // multi-line without parens
};
```

### Objects & Arrays

- Spaces inside braces.
- Trailing commas in multiline.

```javascript
// Good
const person = { name: 'Sebastian', job: 'Developer' };

const person = {
    name: 'Sebastian',
    job: 'Developer',
};

// Bad
const person = {name: 'Sebastian'};
const person = {
    name: 'Sebastian',
    job: 'Developer'  // missing trailing comma
};
```

### Destructuring

Prefer destructuring over property access.

```javascript
// Good
const [hours, minutes] = '12:00'.split(':');

function Component({ name, email, country }) {
    return <div>{name}</div>;
}

// Bad
const time = '12:00'.split(':');
const hours = time[0];
```

### React Components

- Use function components with hooks.
- Props destructuring in parameters.
- File name matches component name (PascalCase).

```jsx
// Good
export default function CarCard({ car, onEdit }) {
    const [isExpanded, setIsExpanded] = useState(false);
    return (
        <div className="car-card">
            <h3>{car.make} {car.model}</h3>
        </div>
    );
}
```

### InertiaJS Patterns

- Use useForm() for form handling.
- Use usePage() to access shared props.
- Always specify Head title.

```jsx
import { Head, useForm, usePage } from '@inertiajs/react';

export default function EditCar({ car }) {
    const { flash } = usePage().props;
    const { data, setData, put, processing, errors } = useForm({
        make: car.make,
        model: car.model,
    });

    return (
        <>
            <Head title={`Edit ${car.make}`} />
            <form onSubmit={(e) => { e.preventDefault(); put(route('cars.update', car.id)); }}>
            </form>
        </>
    );
}
```

### Event Handlers

```jsx
// Good
<button onClick={handleClick}>Save</button>
<button onClick={() => deleteItem(item.id)}>Delete</button>

// Bad — calling function immediately
<button onClick={handleClick()}>Save</button>
```

---

## Database & Eloquent Guidelines

### Migrations

- Always use unsigned for foreign keys and IDs.
- Use foreignId()->constrained()->onDelete('cascade') for relationships.
- Add indexes on frequently queried columns.

```php
// Good
$table->foreignId('user_id')->constrained()->onDelete('cascade');
$table->index('source_hash');
$table->timestamps();
```

### Eloquent

- Type relationships.
- Use fillable or guarded (never both).
- Always define casts for date/datetime/json fields.

```php
// Good
class Car extends Model
{
    protected array $fillable = ['make', 'model', 'year', 'user_id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'current_mileage' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Queries

Prefer Eloquent relationships over raw queries. Use eager loading for N+1 prevention.

```php
// Good
$cars = $user->cars()->with('oilChange')->latest()->get();

// Bad — N+1
$cars = $user->cars;
foreach ($cars as $car) {
    echo $car->oilChange->next_due_date; // Queries in loop!
}
```

---

## Testing Guidelines

- Test class names: FeatureTest or UnitTest suffix.
- Method names: descriptive, test_ prefix, snake_case.
- Use RefreshDatabase for feature tests.
- Assert Inertia component and props.
- Mock external APIs (OpenRouter, OAuth).

```php
// Good
class CarsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_car(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('cars.store'), [
                'make' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2020,
                'current_mileage' => 50000,
                'country' => 'US',
            ]);

        $response->assertRedirect(route('cars.index'));
        $this->assertDatabaseHas('cars', ['make' => 'Toyota']);
    }
}
```

---

## Git & Version Control

### Branches

| Type | Naming | Base |
|------|--------|------|
| Feature | feature-description | develop (pre-live) / master (post-live) |
| Bugfix | fix-description | develop / master |
| Hotfix | hotfix-description | master |

- Master must always be stable (deployable at any time).
- Feature branches: lowercase letters and hyphens only.

### Commits

- Use present tense.
- Be descriptive and granular.

```bash
# Good
git commit -m "Add oil change notification command"
git commit -m "Fix mileage validation to reject lower values"

# Bad
git commit -m "wip"
git commit -m "fix stuff"
```

### Merging

- Prefer squash merges for feature branches.
- Rebase feature branches regularly.

---

## Project-Specific Conventions

### File Organization

```
app/
  Console/Commands/          Artisan commands
  Http/
    Controllers/             Web controllers
    Requests/                Form request validation
    Responses/               Custom Fortify responses
  Models/                    Eloquent models
  Notifications/             Notification classes
  Policies/                  Authorization policies
  Providers/                 Service providers
  Services/                  Business logic services
    OpenRouterService.php
config/
  openrouter.php             OpenRouter-specific config
database/
  factories/                 Model factories
  migrations/                Database migrations
  seeders/                   Database seeders
resources/js/
  Components/                Reusable React components
  Layouts/                   Page layouts
  Pages/                     Inertia pages
    Auth/                    Login, Register
    Cars/                    Index, Create, Edit, Show, Suggestions
    Dashboard.jsx
    Notifications/
routes/
  web.php                    All web routes
tests/
  Feature/                   Feature tests
    Auth/
    CarsTest.php
    EngineSuggestionsTest.php
    NotificationCommandTest.php
    OilChangesTest.php
  Unit/                      Unit tests
```

### Naming Conventions Reference

| Layer | Convention | Example |
|-------|-----------|---------|
| Controllers | PascalCase + Controller | CarsController |
| Form Requests | Action + Resource + Request | StoreCarRequest |
| React Components | PascalCase | CarCard, OilChangeForm |
| React Hooks | camelCase + use prefix | useOilChangeStatus |
| Services | PascalCase + Service | OpenRouterService |
| Tables | snake_case, plural | oil_changes, engine_suggestions |
| Model Methods | camelCase | computeNextDue(), isDue() |
| Route Names | kebab-case | cars.oil-changes.store |
| CSS Classes | Tailwind utility classes | No custom CSS files |

---

## Reminders for AI Agents

1. Always use array notation for validation rules (Spatie standard).
2. Always type-hint properties, parameters, and returns.
3. Never use env() outside config files.
4. Prefer config() for all configuration access.
5. Use __() for all user-facing strings.
6. Keep controllers thin — move logic to services/models.
7. Return Inertia responses from web controllers, never raw JSON.
8. Use useForm() for all React forms.
9. Mock external APIs in tests (OpenRouter, OAuth).
10. Follow PSR-12 for PHP, Spatie JS guide for JavaScript.
