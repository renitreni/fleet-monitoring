<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Ford', 'BMW', 'Mercedes-Benz', 'Hyundai', 'Nissan', 'Volkswagen']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(1990, date('Y') + 1),
            'current_mileage' => fake()->numberBetween(0, 250000),
            'country' => fake()->countryCode(),
            'vin' => fake()->optional()->regexify('[A-HJ-NPR-Z0-9]{17}'),
        ];
    }
}
