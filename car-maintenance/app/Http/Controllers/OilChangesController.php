<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOilChangeRequest;
use App\Http\Requests\UpdateOilChangeRequest;
use App\Models\Car;
use App\Models\OilChange;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;

class OilChangesController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Record a new oil change for the given car.
     *
     * The car must belong to the authenticated user (CarPolicy::update).
     */
    public function store(StoreOilChangeRequest $request, Car $car): RedirectResponse
    {
        $this->authorize('update', $car);

        $oilChange = new OilChange($request->validated());
        $oilChange->car()->associate($car);
        $oilChange->computeNextDue();
        $oilChange->save();

        // Send notification for oil change recorded
        $this->notificationService->send(
            $car->user,
            'oil_change_recorded',
            [
                'car_id' => $car->id,
                'car_make' => $car->make,
                'car_model' => $car->model,
                'next_due_date' => $oilChange->next_due_date->format('M j, Y'),
                'next_due_mileage' => number_format($oilChange->next_due_mileage),
            ]
        );

        return redirect()
            ->route('cars.show', $car)
            ->with('success', sprintf(
                'Oil change recorded. Next service is due on %s or at %s km.',
                $oilChange->next_due_date->format('M j, Y'),
                number_format($oilChange->next_due_mileage),
            ));
    }

    /**
     * Update an existing oil change for the given car.
     *
     * The car must belong to the authenticated user (CarPolicy::update), and
     * the oil change must belong to the car in the route.
     */
    public function update(UpdateOilChangeRequest $request, Car $car, OilChange $oilChange): RedirectResponse
    {
        $this->authorize('update', $car);

        abort_unless($oilChange->car_id === $car->id, 404);

        $oilChange->fill($request->validated());
        $oilChange->computeNextDue();
        $oilChange->save();

        // Send notification for oil change updated
        $this->notificationService->send(
            $car->user,
            'oil_change_recorded',
            [
                'car_id' => $car->id,
                'car_make' => $car->make,
                'car_model' => $car->model,
                'next_due_date' => $oilChange->next_due_date->format('M j, Y'),
                'next_due_mileage' => number_format($oilChange->next_due_mileage),
            ]
        );

        return redirect()
            ->route('cars.show', $car)
            ->with('success', sprintf(
                'Oil change updated. Next service is due on %s or at %s km.',
                $oilChange->next_due_date->format('M j, Y'),
                number_format($oilChange->next_due_mileage),
            ));
    }
}
