<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\OilChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OilChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_away_from_oil_change_routes(): void
    {
        $car = Car::factory()->create();

        $this->post("/cars/{$car->id}/oil-changes")->assertRedirect(route('login'));
        $this->put("/cars/{$car->id}/oil-changes/1")->assertRedirect(route('login'));
    }

    public function test_user_can_record_an_oil_change_and_next_due_is_computed(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create(['current_mileage' => 45000]);

        $response = $this->actingAs($user)->post("/cars/{$car->id}/oil-changes", [
            'last_changed_at' => '2026-03-15',
            'last_changed_mileage' => 45000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);

        $response->assertRedirect(route('cars.show', $car));
        $response->assertSessionHas('success');

        $oilChange = $car->oilChanges()->first();

        $this->assertNotNull($oilChange);
        $this->assertSame('2026-03-15', $oilChange->last_changed_at->format('Y-m-d'));
        $this->assertSame(45000, $oilChange->last_changed_mileage);
        $this->assertSame(6, $oilChange->interval_months);
        $this->assertSame(5000, $oilChange->interval_mileage);
        $this->assertSame('2026-09-15', $oilChange->next_due_date->format('Y-m-d'));
        $this->assertSame(50000, $oilChange->next_due_mileage);
    }

    public function test_store_requires_all_required_fields(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create();

        $this->actingAs($user)
            ->post("/cars/{$car->id}/oil-changes", [])
            ->assertSessionHasErrors(['last_changed_at', 'last_changed_mileage', 'interval_months', 'interval_mileage']);
    }

    public function test_store_rejects_a_future_last_changed_at_date(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create();

        $this->actingAs($user)
            ->post("/cars/{$car->id}/oil-changes", [
                'last_changed_at' => now()->addDay()->format('Y-m-d'),
                'last_changed_mileage' => 10000,
                'interval_months' => 6,
                'interval_mileage' => 5000,
            ])
            ->assertSessionHasErrors(['last_changed_at']);
    }

    public function test_user_cannot_record_an_oil_change_on_another_users_car(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $car = Car::factory()->for($otherUser)->create();

        $this->actingAs($user)->post("/cars/{$car->id}/oil-changes", [
            'last_changed_at' => '2026-03-15',
            'last_changed_mileage' => 10000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ])->assertStatus(403);

        $this->assertDatabaseEmpty('oil_changes');
    }

    public function test_user_can_update_an_oil_change_and_next_due_is_recomputed(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create(['current_mileage' => 45000]);
        $oilChange = OilChange::factory()->for($car)->create();

        $response = $this->actingAs($user)->put("/cars/{$car->id}/oil-changes/{$oilChange->id}", [
            'last_changed_at' => '2026-03-15',
            'last_changed_mileage' => 45000,
            'interval_months' => 3,
            'interval_mileage' => 3000,
        ]);

        $response->assertRedirect(route('cars.show', $car));
        $response->assertSessionHas('success');

        $oilChange->refresh();

        $this->assertSame('2026-03-15', $oilChange->last_changed_at->format('Y-m-d'));
        $this->assertSame(45000, $oilChange->last_changed_mileage);
        $this->assertSame(3, $oilChange->interval_months);
        $this->assertSame(3000, $oilChange->interval_mileage);
        $this->assertSame('2026-06-15', $oilChange->next_due_date->format('Y-m-d'));
        $this->assertSame(48000, $oilChange->next_due_mileage);
    }

    public function test_user_cannot_update_an_oil_change_on_another_users_car(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $car = Car::factory()->for($otherUser)->create();
        $oilChange = OilChange::factory()->for($car)->create();

        $this->actingAs($user)->put("/cars/{$car->id}/oil-changes/{$oilChange->id}", [
            'last_changed_at' => '2026-03-15',
            'last_changed_mileage' => 10000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ])->assertStatus(403);

        $this->assertDatabaseHas('oil_changes', ['id' => $oilChange->id]);
    }

    public function test_updating_an_oil_change_with_a_mismatched_car_returns_404(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create();
        $otherCar = Car::factory()->for($user)->create();
        $oilChange = OilChange::factory()->for($car)->create();

        $this->actingAs($user)->put("/cars/{$otherCar->id}/oil-changes/{$oilChange->id}", [
            'last_changed_at' => '2026-03-15',
            'last_changed_mileage' => 10000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ])->assertStatus(404);
    }

    public function test_compute_next_due_calculates_the_correct_date_and_mileage(): void
    {
        $car = Car::factory()->create(['current_mileage' => 30000]);

        $oilChange = new OilChange([
            'last_changed_at' => '2026-01-15',
            'last_changed_mileage' => 25000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);
        $oilChange->car()->associate($car);
        $oilChange->computeNextDue();

        $this->assertSame('2026-07-15', $oilChange->next_due_date->format('Y-m-d'));
        $this->assertSame(30000, $oilChange->next_due_mileage);
    }

    public function test_is_due_returns_true_when_due_date_has_passed(): void
    {
        $car = Car::factory()->create(['current_mileage' => 10000]);
        $oilChange = OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonths(2)->format('Y-m-d'),
            'last_changed_mileage' => 8000,
            'interval_months' => 1,
            'interval_mileage' => 10000,
            'next_due_date' => now()->subMonth()->format('Y-m-d'),
            'next_due_mileage' => 18000,
        ]);

        $this->assertTrue($oilChange->isDue());
    }

    public function test_is_due_returns_true_when_current_mileage_passes_due_mileage(): void
    {
        $car = Car::factory()->create(['current_mileage' => 20000]);
        $oilChange = OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonths(2)->format('Y-m-d'),
            'last_changed_mileage' => 10000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
            'next_due_date' => now()->addMonths(4)->format('Y-m-d'),
            'next_due_mileage' => 15000,
        ]);

        $this->assertTrue($oilChange->isDue());

        $car->update(['current_mileage' => 14000]);

        $this->assertFalse($oilChange->fresh()->isDue());
    }

    public function test_is_due_returns_false_when_still_within_intervals(): void
    {
        $car = Car::factory()->create(['current_mileage' => 10000]);
        $oilChange = OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonth()->format('Y-m-d'),
            'last_changed_mileage' => 8000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
            'next_due_date' => now()->addMonths(5)->format('Y-m-d'),
            'next_due_mileage' => 13000,
        ]);

        $this->assertFalse($oilChange->isDue());
    }

    public function test_dashboard_displays_a_car_with_an_oil_change(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create(['current_mileage' => 10000]);
        OilChange::factory()->for($car)->create([
            'next_due_date' => now()->addMonths(5)->format('Y-m-d'),
            'next_due_mileage' => 15000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
