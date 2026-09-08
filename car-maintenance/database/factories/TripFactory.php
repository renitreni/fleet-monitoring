<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TripFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'name' => fake()->words(3, true), 'description' => 'A group road trip.',
            'route_points' => [['latitude' => 0, 'longitude' => 0], ['latitude' => 0, 'longitude' => 0.002], ['latitude' => 0, 'longitude' => 0.004]],
            'checkpoints' => [['name' => 'Start', 'point_index' => 0, 'distance_m' => 0], ['name' => 'Meet up', 'point_index' => 1, 'distance_m' => 222.389853289], ['name' => 'Finish', 'point_index' => 2, 'distance_m' => 444.779706578]],
            'distance_m' => 444.779706578, 'invite_token' => Str::random(64), 'is_public' => false, 'ends_at' => now()->addDay(),
        ];
    }
}
