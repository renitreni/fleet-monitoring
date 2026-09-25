<?php

namespace Database\Factories;

use App\Models\AnalyticsPageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsPageView>
 */
class AnalyticsPageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_hash' => hash('sha256', fake()->uuid()),
            'route_name' => 'home',
            'route_uri' => '/',
            'referrer_host' => fake()->optional()->domainName(),
            'occurred_at' => now(),
        ];
    }
}
