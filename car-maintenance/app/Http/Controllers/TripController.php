<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripParticipant;
use App\Services\RouteProgress;
use App\Services\TripStandings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Trips/Index', ['trips' => Trip::whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id)->whereNull('left_at'))->latest('id')->paginate(20)->through(fn (Trip $trip) => [
            'id' => $trip->id, 'name' => $trip->name, 'distance_km' => round($trip->distance_m / 1000, 1), 'open' => $trip->isOpen(), 'url' => route('trips.show', $trip),
        ])]);
    }

    public function invite(string $token): Response
    {
        $trip = Trip::where('invite_token', $token)->with('user:id,name')->firstOrFail();
        abort_unless($trip->isOpen(), 410, 'This invitation has expired or the trip has ended.');

        return inertia('Trips/Join', ['trip' => ['name' => $trip->name, 'description' => $trip->description, 'creator' => $trip->user?->name, 'distance_km' => round($trip->distance_m / 1000, 1), 'checkpoint_count' => count($trip->checkpoints), 'is_public' => $trip->is_public], 'joinUrl' => route('trips.join', $token)]);
    }

    public function join(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate(['public_consent' => ['required', 'boolean']]);
        $trip = DB::transaction(function () use ($request, $token, $data) {
            $trip = Trip::where('invite_token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($trip->isOpen(), 410);
            $participant = $trip->participants()->firstOrCreate(['user_id' => $request->user()->id], $data);
            if ($participant->left_at) {
                $participant->forceFill(['left_at' => null, 'public_consent' => $data['public_consent']])->save();
            }

            return $trip;
        });

        return redirect()->route('trips.show', $trip);
    }

    public function show(Request $request, Trip $trip, TripStandings $standings, RouteProgress $geometry): Response
    {
        $this->authorize('view', $trip);
        $participant = $trip->participants()->where('user_id', $request->user()->id)->firstOrFail();

        Inertia::encryptHistory();

        return inertia('Trips/Show', [
            'trip' => ['id' => $trip->id, 'name' => $trip->name, 'description' => $trip->description, 'route_points' => $trip->route_points, 'checkpoints' => $trip->checkpoints, 'distance_m' => $trip->distance_m, 'open' => $trip->isOpen(), 'is_route' => $trip->is_route, 'ends_at' => $trip->ends_at?->toIso8601String(), 'creator' => $trip->user?->name],
            'standings' => $trip->is_route ? array_values(array_filter($standings->rows($trip), fn ($row) => $row['user_id'] === $request->user()->id)) : $standings->rows($trip),
            'timedStandings' => $trip->is_route ? $standings->times($trip) : [],
            'attempts' => $trip->is_route ? $participant->attempts()->latest('id')->limit(20)->get(['id', 'status', 'started_at', 'finished_at', 'elapsed_ms']) : [],
            'personalBest' => $trip->is_route ? $participant->attempts()->where('status', 'completed')->min('elapsed_ms') : null,
            'participant' => ['id' => $participant->id, 'public_consent' => $participant->public_consent, 'completed' => $participant->completed_at !== null, 'resume_point' => $geometry->pointAt($trip->route_points, $participant->progress_m)],
            'urls' => collect(['start', 'stop', 'location', 'leave', 'consent', 'cancel'])->mapWithKeys(fn ($action) => [$action => route('trips.'.$action, $trip)]),
        ]);
    }

    public function leave(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('view', $trip);
        DB::transaction(function () use ($request, $trip) {
            Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $participant = TripParticipant::where('trip_id', $trip->id)->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $participant->forceFill(['left_at' => now(), 'tracking' => false, 'tracking_token' => null, 'last_fix' => null, 'verified_fix' => null, 'continuous' => false, 'public_consent' => false, 'last_received_at' => null, 'last_recorded_at' => null])->save();
            $participant->attempts()->whereIn('status', ['ready', 'active'])->update(['status' => 'cancelled']);
            $participant->locations()->delete();
        });

        Inertia::clearHistory();

        return redirect()->route('trips.index')->with('success', 'You left the trip. Your location records were deleted.');
    }

    public function consent(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('view', $trip);
        $data = $request->validate(['public_consent' => ['required', 'boolean']]);
        $trip->participants()->where('user_id', $request->user()->id)->whereNull('left_at')->update($data);

        return back()->with('success', 'Public leaderboard preference updated.');
    }
}
