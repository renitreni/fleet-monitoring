<?php

namespace Database\Factories;

use App\Models\TripParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteAttemptFactory extends Factory
{
    public function definition(): array
    {
        return ['trip_participant_id' => TripParticipant::factory(), 'status' => 'ready'];
    }
}
