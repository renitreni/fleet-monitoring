<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\OilChange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilChange>
 */
class OilChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lastChangedAt = fake()->dateTimeBetween('-2 years', 'now');
        $intervalMonths = fake()->numberBetween(3, 12);
        $lastChangedMileage = fake()->numberBetween(0, 180000);
        $intervalMileage = fake()->numberBetween(3000, 10000);

        return [
            'car_id' => Car::factory(),
            'last_changed_at' => $lastChangedAt,
            'last_changed_mileage' => $lastChangedMileage,
            'interval_months' => $intervalMonths,
            'interval_mileage' => $intervalMileage,
            'next_due_date' => Carbon::parse($lastChangedAt)->addMonths($intervalMonths)->format('Y-m-d'),
            'next_due_mileage' => $lastChangedMileage + $intervalMileage,
        ];
    }
}
