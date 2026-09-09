<?php

namespace App\Http\Controllers;

use App\Models\RouteAttempt;
use App\Models\Trip;
use App\Services\TripStandings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class RouteCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:newest,popular,shortest,longest,name'],
            'filter' => ['nullable', 'in:all,joined'],
        ]);
        $sort = $filters['sort'] ?? 'newest';
        $filter = $filters['filter'] ?? 'all';
        $query = Trip::where('is_route', true)->where('is_public', true)
            ->when($filter !== 'joined', fn ($query) => $query->whereNull('closed_at'))
            ->withCount(['participants' => fn ($query) => $query->whereNull('left_at')])
            ->withExists(['participants as joined' => fn ($query) => $query->where('user_id', $request->user()?->id)->whereNull('left_at')])
            ->addSelect(['best_time' => RouteAttempt::selectRaw('MIN(elapsed_ms)')->where('status', 'completed')
                ->whereHas('participant', fn ($query) => $query->whereColumn('trip_id', 'trips.id')->where('public_consent', true)->whereNull('left_at'))])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($filter === 'joined', fn ($query) => $query->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()?->id)->whereNull('left_at')));
        [$column, $direction] = match ($sort) {
            'popular' => ['participants_count', 'desc'],
            'shortest' => ['distance_m', 'asc'],
            'longest' => ['distance_m', 'desc'],
            'name' => ['name', 'asc'],
            default => ['id', 'desc'],
        };

        return inertia('Trips/Marketplace', [
            'routes' => $query->orderBy($column, $direction)->orderByDesc('id')->paginate(12)->withQueryString()->through(fn (Trip $trip) => [
                'id' => $trip->id, 'name' => $trip->name, 'description' => $trip->description,
                'route_points' => $trip->route_points, 'distance_km' => round($trip->distance_m / 1000, 1),
                'participants_count' => $trip->participants_count, 'best_time' => $trip->best_time,
                'joined' => (bool) $trip->joined, 'open' => $trip->isOpen(),
                'url' => route('routes.show', $trip), 'join_url' => route('routes.join', $trip),
                'attempt_url' => route('trips.show', $trip),
            ]),
            'filters' => ['search' => $filters['search'] ?? '', 'sort' => $sort, 'filter' => $filter],
            'catalogUrl' => route('routes.index'),
        ]);
    }

    public function show(Request $request, Trip $trip, TripStandings $standings): Response
    {
        abort_unless($trip->is_route && $trip->is_public, 404);
        $participant = $request->user() ? $trip->participants()->where('user_id', $request->user()->id)->whereNull('left_at')->first() : null;
        // Remember the selected route for the normal authentication redirect.
        if (! $request->user()) {
            $request->session()->put('url.intended', route('routes.show', $trip));
        }

        return inertia('Trips/Route', [
            'trip' => [
                'name' => $trip->name, 'description' => $trip->description,
                'route_points' => $trip->route_points, 'checkpoints' => $trip->checkpoints,
                'distance_km' => round($trip->distance_m / 1000, 1), 'open' => $trip->isOpen(),
                'creator' => $trip->user?->name, 'participants_count' => $trip->participants()->whereNull('left_at')->count(),
            ],
            'standings' => $standings->times($trip), 'joined' => $participant !== null,
            'joinUrl' => route('routes.join', $trip), 'attemptUrl' => route('trips.show', $trip),
            'personalBest' => $participant?->attempts()->where('status', 'completed')->min('elapsed_ms'),
        ]);
    }

    public function join(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate(['public_consent' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($request, $trip, $data) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            abort_unless($trip->is_route && $trip->is_public, 404);
            abort_unless($trip->isOpen(), 409, 'This route is archived.');
            $participant = $trip->participants()->firstOrCreate(['user_id' => $request->user()->id], ['public_consent' => $data['public_consent'] ?? false]);
            if ($participant->left_at) {
                $participant->forceFill(['left_at' => null, 'public_consent' => $data['public_consent'] ?? false])->save();
            }
        });

        return redirect()->route('trips.show', $trip);
    }
}
