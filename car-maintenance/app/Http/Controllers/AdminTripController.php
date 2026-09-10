<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Models\RouteAttempt;
use App\Models\Trip;
use App\Services\RouteProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Response;

class AdminTripController extends Controller
{
    public function index(): Response
    {
        $this->authorize('create', Trip::class);

        return inertia('Trips/Admin', ['trips' => Trip::with('user:id,name')->withCount('participants')->latest('id')->paginate(20)->through(fn (Trip $trip) => [
            'id' => $trip->id, 'name' => $trip->name, 'creator' => $trip->user?->name, 'open' => $trip->isOpen(), 'is_public' => $trip->is_public, 'participants_count' => $trip->participants_count,
            'is_route' => $trip->is_route,
            'route_url' => $trip->is_route ? route('routes.show', $trip) : null,
            'manage_url' => route('admin.trips.update', $trip),
        ]), 'storeUrl' => route('admin.trips.store')]);
    }

    public function store(StoreTripRequest $request, RouteProgress $geometry): RedirectResponse
    {
        $data = $request->validated();
        $distances = $geometry->distances($data['route_points']);
        $data['checkpoints'] = array_map(fn ($checkpoint) => [...$checkpoint, 'distance_m' => $distances[$checkpoint['point_index']]], $data['checkpoints']);
        $trip = Trip::create([...$data, 'distance_m' => end($distances), 'user_id' => $request->user()->id, 'invite_token' => Str::random(64), 'is_route' => true, 'is_public' => true, 'ends_at' => null]);

        return redirect()->route('admin.trips.index')->with('success', "{$trip->name} is published. Account holders can join from Browse routes.");
    }

    public function update(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);
        $data = $request->validate(['action' => ['required', 'in:close,rotate,visibility,publish'], 'is_public' => ['required_if:action,visibility', 'boolean']]);
        DB::transaction(function () use ($trip, $data) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            if ($data['action'] === 'publish') {
                abort_if($trip->is_route, 409);
                Trip::create([
                    'user_id' => $trip->user_id,
                    'name' => $trip->name, 'description' => $trip->description,
                    'route_points' => $trip->route_points, 'checkpoints' => $trip->checkpoints,
                    'distance_m' => $trip->distance_m, 'invite_token' => Str::random(64),
                    'is_route' => true, 'is_public' => true, 'ends_at' => null,
                ]);

                return;
            }
            if ($data['action'] === 'close') {
                RouteAttempt::whereHas('participant', fn ($query) => $query->where('trip_id', $trip->id))
                    ->whereIn('status', ['ready', 'active'])->update(['status' => 'cancelled']);
                $trip->closed_at = now();
                $trip->participants()->update(['tracking' => false, 'tracking_token' => null, 'last_fix' => null, 'verified_fix' => null, 'continuous' => false]);
            } elseif ($data['action'] === 'rotate') {
                $trip->invite_token = Str::random(64);
            } else {
                $trip->is_public = $data['is_public'];
            }
            $trip->save();
        });

        return back()->with('success', 'Route settings updated.');
    }
}
