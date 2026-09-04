<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\OilSuggestion;
use App\Models\User;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class OilSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttle middleware uses the array cache driver in tests, which
        // persists across tests in the same process. Flush it before each test
        // so rate-limit assertions start from a clean slate.
        Cache::flush();
    }

    /**
     * A valid AI payload for a car.
     */
    protected function suggestionPayload(): array
    {
        return [
            'viscosity' => '0W-20',
            'oil_type' => 'Full synthetic',
            'specification' => 'API SP / ILSAC GF-6A',
            'capacity_liters' => 4.2,
            'interval_months' => 12,
            'interval_kilometers' => 10000,
            'interval_basis' => 'Manufacturer normal-service schedule.',
            'products' => [
                ['brand' => 'Toyota', 'product' => 'Toyota Genuine Motor Oil 0W-20', 'role' => 'Assigned product', 'reason' => 'OEM oil.'],
                ['brand' => 'Mobil 1', 'product' => 'Mobil 1 Advanced Fuel Economy 0W-20', 'role' => 'Alternative', 'reason' => 'Compatible.'],
                ['brand' => 'Castrol', 'product' => 'Castrol EDGE 0W-20 Advanced Full Synthetic', 'role' => 'Alternative', 'reason' => 'Compatible.'],
            ],
            'notes' => 'Always confirm with the owner manual.',
        ];
    }

    /**
     * Create a car with a fixed specification so hashes are predictable.
     */
    protected function createCar(User $user): Car
    {
        return Car::factory()->for($user)->create([
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'country' => 'US',
            'current_mileage' => 45000,
        ]);
    }

    public function test_guests_are_redirected_away_from_oil_suggestion_routes(): void
    {
        $car = Car::factory()->create();

        $this->get("/cars/{$car->id}/oil-suggestions")->assertRedirect(route('login'));
        $this->post("/cars/{$car->id}/oil-suggestions/generate")->assertRedirect(route('login'));
    }

    public function test_user_cannot_view_or_generate_suggestions_for_another_users_car(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $car = Car::factory()->for($otherUser)->create();

        $this->actingAs($user)->get("/cars/{$car->id}/oil-suggestions")->assertStatus(403);
        $this->actingAs($user)->post("/cars/{$car->id}/oil-suggestions/generate")->assertStatus(403);

        $this->assertDatabaseEmpty('oil_suggestions');
    }

    public function test_index_shows_empty_state_when_no_suggestion_is_cached(): void
    {
        $user = User::factory()->create();
        $car = $this->createCar($user);

        $this->actingAs($user)
            ->get("/cars/{$car->id}/oil-suggestions")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Cars/OilSuggestions', false)
                    ->where('car.id', $car->id)
                    ->where('suggestion', null)
            );
    }

    public function test_generate_calls_the_api_once_and_caches_the_result_forever(): void
    {
        $user = User::factory()->create();
        $car = $this->createCar($user);

        $payload = $this->suggestionPayload();
        $this->mock(OpenRouterService::class, function (Mockery\MockInterface $mock) use ($payload) {
            $mock->shouldReceive('getOilSuggestions')
                ->once()
                ->with('Toyota', 'Corolla', 2020, 'US', Mockery::type('int'))
                ->andReturn($payload);
        });

        $response = $this->actingAs($user)->post("/cars/{$car->id}/oil-suggestions/generate");

        $response->assertRedirect(route('cars.oil-suggestions.index', $car));
        $response->assertSessionHas('success');

        $suggestion = OilSuggestion::first();

        $this->assertNotNull($suggestion);
        $this->assertSame($car->id, $suggestion->car_id);
        $this->assertSame(OilSuggestion::sourceHashFor($car), $suggestion->source_hash);
        $this->assertSame('0W-20', $suggestion->suggestions_json['viscosity']);
        $this->assertSame(12, $suggestion->suggestions_json['interval_months']);
        $this->assertSame(10000, $suggestion->suggestions_json['interval_kilometers']);
        $this->assertCount(3, $suggestion->suggestions_json['products']);
        $this->assertSame('Toyota Genuine Motor Oil 0W-20', $suggestion->suggestions_json['products'][0]['product']);

        // A second request is served entirely from the cache — no API call.
        $this->actingAs($user)
            ->post("/cars/{$car->id}/oil-suggestions/generate")
            ->assertRedirect(route('cars.oil-suggestions.index', $car))
            ->assertSessionHas('success');

        $this->assertSame(1, OilSuggestion::count());
    }

    public function test_cached_suggestions_are_shared_between_cars_with_the_same_specification(): void
    {
        $user = User::factory()->create();
        $firstCar = $this->createCar($user);
        $secondCar = $this->createCar($user);

        $payload = $this->suggestionPayload();
        $this->mock(OpenRouterService::class, function (Mockery\MockInterface $mock) use ($payload) {
            // Only one AI call for both cars with the identical specification.
            $mock->shouldReceive('getOilSuggestions')->once()->andReturn($payload);
        });

        $this->actingAs($user)->post("/cars/{$firstCar->id}/oil-suggestions/generate");
        $this->actingAs($user)
            ->post("/cars/{$secondCar->id}/oil-suggestions/generate")
            ->assertSessionHas('success');

        $this->assertSame(1, OilSuggestion::count());

        // The second car sees the cached suggestion without owning the row.
        $this->actingAs($user)
            ->get("/cars/{$secondCar->id}/oil-suggestions")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Cars/OilSuggestions', false)
                    ->where('suggestion.suggestions_json.viscosity', '0W-20')
            );
    }

    public function test_force_parameter_is_ignored_and_the_cache_is_used_forever(): void
    {
        $user = User::factory()->create();
        $car = $this->createCar($user);

        OilSuggestion::factory()->for($car)->create([
            'source_hash' => OilSuggestion::sourceHashFor($car),
        ]);

        $this->mock(OpenRouterService::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('getOilSuggestions')->never();
        });

        $this->actingAs($user)
            ->post("/cars/{$car->id}/oil-suggestions/generate?force=1")
            ->assertRedirect(route('cars.oil-suggestions.index', $car))
            ->assertSessionHas('success');

        $this->assertSame(1, OilSuggestion::count());
    }

    public function test_failed_api_call_saves_nothing_and_flashes_an_error(): void
    {
        $user = User::factory()->create();
        $car = $this->createCar($user);

        $this->mock(OpenRouterService::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('getOilSuggestions')->once()->andReturnNull();
        });

        $this->actingAs($user)
            ->post("/cars/{$car->id}/oil-suggestions/generate")
            ->assertRedirect(route('cars.oil-suggestions.index', $car))
            ->assertSessionHas('error');

        $this->assertDatabaseEmpty('oil_suggestions');
    }

    public function test_generate_endpoint_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $car = $this->createCar($user);

        $this->mock(OpenRouterService::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('getOilSuggestions')->andReturnNull();
        });

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->post("/cars/{$car->id}/oil-suggestions/generate")
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post("/cars/{$car->id}/oil-suggestions/generate")
            ->assertStatus(429);
    }
}
