<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarRequest;
use App\Models\Car;
use App\Models\OilSuggestion;
use App\Models\User;
use App\Services\CarCreationQuota;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class CarsController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected CarCreationQuota $carCreationQuota,
    ) {}

    /**
     * Display a listing of the authenticated user's cars.
     */
    public function index(Request $request): Response
    {
        return inertia('Cars/Index', [
            'cars' => $request->user()
                ->cars()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    /**
     * Show the form for creating a new car.
     */
    public function create(Request $request): Response
    {
        return inertia('Cars/Create', [
            'carAllowance' => [
                'limit' => $this->carCreationQuota->limit($request->user()),
                'remaining' => $this->carCreationQuota->remaining($request->user()),
                'resetsAt' => now()->endOfWeek()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Store a newly created car.
     */
    public function store(StoreCarRequest $request): RedirectResponse
    {
        $car = DB::transaction(function () use ($request): Car {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            abort_unless(
                $this->carCreationQuota->canCreate($user),
                429,
                'Free accounts can add up to 3 cars per week. Your allowance resets next week.'
            );

            $car = $user->cars()->create($request->validated());
            $this->carCreationQuota->record($user, $car);

            return $car;
        });

        // Send notification for car added
        $this->notificationService->send(
            $request->user(),
            'car_added',
            [
                'car_id' => $car->id,
                'car_make' => $car->make,
                'car_model' => $car->model,
                'car_year' => $car->year,
            ]
        );

        return redirect()
            ->route('cars.index')
            ->with('success', "Your {$car->year} {$car->make} {$car->model} was added to your garage.");
    }

    /**
     * Display the specified car.
     */
    public function show(Request $request, Car $car): Response
    {
        $this->authorize('view', $car);

        // Load the latest oil change relationship to make it available in the frontend
        $car->load('oilChanges');
        $suggestion = OilSuggestion::where('source_hash', OilSuggestion::sourceHashFor($car))->first();

        return inertia('Cars/Show', [
            'car' => $car,
            'recommendedInterval' => $suggestion?->suggestions_json,
        ]);
    }

    /**
     * Show the form for editing the specified car.
     */
    public function edit(Request $request, Car $car): Response
    {
        $this->authorize('update', $car);

        return inertia('Cars/Edit', [
            'car' => $car,
        ]);
    }

    /**
     * Update the specified car.
     */
    public function update(StoreCarRequest $request, Car $car): RedirectResponse
    {
        $this->authorize('update', $car);

        $car->update($request->validated());

        return redirect()
            ->route('cars.show', $car)
            ->with('success', "Your {$car->year} {$car->make} {$car->model} was updated.");
    }

    /**
     * Remove the specified car.
     */
    public function destroy(Request $request, Car $car): RedirectResponse
    {
        $this->authorize('delete', $car);

        $car->delete();

        return redirect()
            ->route('cars.index')
            ->with('success', 'Your car was deleted from your garage.');
    }
}
