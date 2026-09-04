<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarCreation;
use App\Models\OilSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CarsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_away_from_cars_pages(): void
    {
        $this->get('/cars')->assertRedirect(route('login'));
        $this->get('/cars/create')->assertRedirect(route('login'));
        $this->post('/cars')->assertRedirect(route('login'));
    }

    public function test_index_renders_only_the_authenticated_users_cars(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Car::factory()->for($user)->create(['make' => 'Toyota', 'model' => 'Corolla']);
        Car::factory()->for($otherUser)->create(['make' => 'Ford', 'model' => 'Focus']);

        // The frontend pages are built on Day 12, so component existence is not asserted yet.
        $this->actingAs($user)
            ->get('/cars')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Cars/Index', false)
                    ->has('cars', 1)
                    ->where('cars.0.make', 'Toyota')
                    ->where('cars.0.model', 'Corolla')
            );
    }

    public function test_create_renders_the_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/cars/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Cars/Create', false));
    }

    public function test_user_can_store_a_new_car(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/cars', [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
            'country' => 'US',
            'vin' => '1HGCV1F34LA012345',
        ]);

        $response->assertRedirect(route('cars.index'));
        $response->assertSessionHas('success', 'Your 2020 Honda Civic was added to your garage.');

        $this->assertDatabaseHas('cars', [
            'user_id' => $user->id,
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
            'country' => 'US',
            'vin' => '1HGCV1F34LA012345',
        ]);

        $this->assertSame(1, $user->cars()->count());
        $this->assertDatabaseHas('car_creations', [
            'user_id' => $user->id,
            'car_id' => $user->cars()->first()->id,
        ]);
    }

    public function test_free_user_cannot_add_more_than_three_cars_in_a_week(): void
    {
        $this->travelTo('2026-09-02 10:00:00');
        $user = User::factory()->create();
        CarCreation::factory()->count(3)->for($user)->create();

        $response = $this->actingAs($user)->post('/cars', [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
            'country' => 'US',
        ]);

        $response->assertTooManyRequests();
        $this->assertSame(0, $user->cars()->count());
    }

    public function test_previous_week_car_additions_do_not_use_this_weeks_allowance(): void
    {
        $this->travelTo('2026-09-09 10:00:00');
        $user = User::factory()->create();
        CarCreation::factory()->count(3)->for($user)->create([
            'created_at' => now()->subWeek(),
        ]);

        $this->actingAs($user)->post('/cars', [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
            'country' => 'US',
        ])->assertRedirect(route('cars.index'));

        $this->assertSame(1, $user->cars()->count());
    }

    public function test_premium_user_has_no_weekly_car_limit(): void
    {
        $user = User::factory()->premium()->create();
        CarCreation::factory()->count(3)->for($user)->create();

        $this->actingAs($user)->post('/cars', [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
            'country' => 'US',
        ])->assertRedirect(route('cars.index'));

        $this->assertSame(1, $user->cars()->count());
    }

    public function test_create_page_reports_the_remaining_weekly_allowance(): void
    {
        $user = User::factory()->create();
        CarCreation::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('/cars/create')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('carAllowance.limit', 3)
                    ->where('carAllowance.remaining', 2)
            );
    }

    public function test_blank_optional_vin_is_stored_as_null(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/cars', [
            'make' => 'Honda',
            'model' => 'Accord',
            'year' => 2018,
            'current_mileage' => 0,
            'country' => 'DE',
            'vin' => '',
        ])->assertRedirect(route('cars.index'));

        $this->assertDatabaseHas('cars', [
            'user_id' => $user->id,
            'vin' => null,
        ]);
    }

    public function test_store_requires_all_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/cars', [])
            ->assertSessionHasErrors(['make', 'model', 'year', 'current_mileage', 'country']);
    }

    public function test_store_rejects_invalid_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/cars', [
            'make' => '',
            'model' => '',
            'year' => 1800,
            'current_mileage' => -5,
            'country' => 'USA',
            'vin' => '1234567890123456789',
        ])->assertSessionHasErrors(['make', 'model', 'year', 'current_mileage', 'country', 'vin']);

        $this->assertDatabaseCount('cars', 0);
    }

    public function test_store_rejects_years_beyond_one_year_in_the_future(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/cars', [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => date('Y') + 2,
            'current_mileage' => 0,
            'country' => 'US',
        ])->assertSessionHasErrors('year');
    }

    public function test_guests_are_redirected_away_from_single_car_pages(): void
    {
        $car = Car::factory()->create();

        $this->get("/cars/{$car->id}")->assertRedirect(route('login'));
        $this->get("/cars/{$car->id}/edit")->assertRedirect(route('login'));
        $this->put("/cars/{$car->id}")->assertRedirect(route('login'));
        $this->delete("/cars/{$car->id}")->assertRedirect(route('login'));
    }

    public function test_show_renders_the_car_detail_page(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
            'country' => 'US',
            'vin' => '1HGCV1F34LA012345',
        ]);

        $this->actingAs($user)
            ->get("/cars/{$car->id}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Cars/Show', false)
                    ->where('car.id', $car->id)
                    ->where('car.make', 'Honda')
                    ->where('car.model', 'Civic')
                    ->where('car.year', 2020)
                    ->where('car.current_mileage', 45000)
                    ->where('car.country', 'US')
                    ->where('car.vin', '1HGCV1F34LA012345')
            );
    }

    public function test_show_provides_cached_ai_interval_for_the_oil_change_form(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create();
        OilSuggestion::factory()->for($car)->create([
            'source_hash' => OilSuggestion::sourceHashFor($car),
            'suggestions_json' => [
                'interval_months' => 12,
                'interval_kilometers' => 10000,
            ],
        ]);

        $this->actingAs($user)
            ->get("/cars/{$car->id}")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('recommendedInterval.interval_months', 12)
                    ->where('recommendedInterval.interval_kilometers', 10000)
            );
    }

    public function test_user_cannot_view_another_users_car(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $car = Car::factory()->for($otherUser)->create();

        $this->actingAs($user)->get("/cars/{$car->id}")->assertStatus(403);
    }

    public function test_edit_renders_the_form_with_the_car_pre_populated(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
            'country' => 'US',
            'vin' => '1HGCV1F34LA012345',
        ]);

        $this->actingAs($user)
            ->get("/cars/{$car->id}/edit")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Cars/Edit', false)
                    ->where('car.id', $car->id)
                    ->where('car.make', 'Honda')
                    ->where('car.model', 'Civic')
                    ->where('car.year', 2020)
                    ->where('car.current_mileage', 45000)
                    ->where('car.country', 'US')
                    ->where('car.vin', '1HGCV1F34LA012345')
            );
    }

    public function test_user_cannot_edit_another_users_car(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $car = Car::factory()->for($otherUser)->create();

        $this->actingAs($user)->get("/cars/{$car->id}/edit")->assertStatus(403);
    }

    public function test_user_can_update_their_car(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
            'country' => 'US',
            'vin' => '1HGCV1F34LA012345',
        ]);

        $response = $this->actingAs($user)->put("/cars/{$car->id}", [
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2021,
            'current_mileage' => 50000,
            'country' => 'CA',
            'vin' => '2T1BURHE0KC123456',
        ]);

        $response->assertRedirect(route('cars.show', $car));
        $response->assertSessionHas('success', 'Your 2021 Toyota Corolla was updated.');

        $this->assertDatabaseHas('cars', [
            'id' => $car->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2021,
            'current_mileage' => 50000,
            'country' => 'CA',
            'vin' => '2T1BURHE0KC123456',
        ]);
    }

    public function test_user_cannot_update_another_users_car(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $car = Car::factory()->for($otherUser)->create();

        $this->actingAs($user)->put("/cars/{$car->id}", [
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2021,
            'current_mileage' => 50000,
            'country' => 'CA',
        ])->assertStatus(403);

        $this->assertDatabaseHas('cars', [
            'id' => $car->id,
            'make' => $car->make,
            'model' => $car->model,
        ]);
    }

    public function test_update_validates_the_car_data(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create();

        $this->actingAs($user)->put("/cars/{$car->id}", [
            'make' => '',
            'model' => '',
            'year' => 1800,
            'current_mileage' => -1,
            'country' => 'USA',
        ])->assertSessionHasErrors(['make', 'model', 'year', 'current_mileage', 'country']);
    }

    public function test_user_can_delete_their_car(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        $response = $this->actingAs($user)->delete("/cars/{$car->id}");

        $response->assertRedirect(route('cars.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('cars', ['id' => $car->id]);
    }

    public function test_user_cannot_delete_another_users_car(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $car = Car::factory()->for($otherUser)->create();

        $this->actingAs($user)->delete("/cars/{$car->id}")->assertStatus(403);

        $this->assertDatabaseHas('cars', ['id' => $car->id]);
    }
}
