<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\OilSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilSuggestion>
 */
class OilSuggestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'source_hash' => fake()->sha256(),
            'suggestions_json' => [
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
                'notes' => fake()->sentence(),
            ],
        ];
    }
}
