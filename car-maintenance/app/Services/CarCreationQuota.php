<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarCreation;
use App\Models\User;

class CarCreationQuota
{
    public function limit(User $user): ?int
    {
        return $user->isPremium() ? null : config('plans.free_weekly_car_limit');
    }

    public function used(User $user): int
    {
        return CarCreation::whereBelongsTo($user)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
    }

    public function remaining(User $user): ?int
    {
        $limit = $this->limit($user);

        return $limit === null ? null : max(0, $limit - $this->used($user));
    }

    public function canCreate(User $user): bool
    {
        $remaining = $this->remaining($user);

        return $remaining === null || $remaining > 0;
    }

    public function record(User $user, Car $car): void
    {
        CarCreation::create([
            'user_id' => $user->id,
            'car_id' => $car->id,
        ]);
    }
}
