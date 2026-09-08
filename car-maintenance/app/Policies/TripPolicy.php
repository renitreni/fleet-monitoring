<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TripPolicy
{
    public function create(User $user): bool
    {
        return (bool) $user->is_trip_admin;
    }

    public function update(User $user, Trip $trip): bool
    {
        return (bool) $user->is_trip_admin;
    }

    public function view(User $user, Trip $trip): Response
    {
        return $trip->participants()->where('user_id', $user->id)->whereNull('left_at')->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }
}
