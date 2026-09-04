<?php

namespace Database\Factories;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notifiable_id' => User::factory(),
            'notifiable_type' => User::class,
            'notification_type' => fake()->randomElement(['oil_change_due', 'oil_change_overdue']),
            'data' => null,
            'sent_at' => now(),
            'read_at' => fake()->optional(0.3)->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
