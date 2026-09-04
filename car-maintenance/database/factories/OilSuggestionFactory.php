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
                'brands' => [
                    ['name' => 'Mobil 1', 'notes' => 'OEM-equivalent full synthetic.'],
                    ['name' => 'Castrol EDGE', 'notes' => 'Widely available full synthetic.'],
                ],
                'notes' => fake()->sentence(),
            ],
        ];
    }
}
