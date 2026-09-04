<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    /**
     * Determine whether the user can view the car.
     */
    public function view(User $user, Car $car): bool
    {
        return $car->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the car.
     */
    public function update(User $user, Car $car): bool
    {
        return $car->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the car.
     */
    public function delete(User $user, Car $car): bool
    {
        return $car->user_id === $user->id;
    }
}
