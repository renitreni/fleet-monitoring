<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripParticipantFactory extends Factory
{
    public function definition(): array
    {
        return ['trip_id' => Trip::factory(), 'user_id' => User::factory(), 'public_consent' => false];
    }
}
