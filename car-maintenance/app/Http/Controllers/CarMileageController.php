<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCarMileageRequest;
use App\Models\Car;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;

class CarMileageController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Update the current mileage for the specified car.
     */
    public function update(UpdateCarMileageRequest $request, Car $car): RedirectResponse
    {
        $this->authorize('update', $car);

        $car->update($request->validated());

        // Send notification for mileage updated
        $this->notificationService->send(
            $car->user,
            'mileage_updated',
            [
                'car_id' => $car->id,
                'car_make' => $car->make,
                'car_model' => $car->model,
                'current_mileage' => number_format($car->current_mileage),
            ]
        );

        return redirect()
            ->route('cars.show', $car)
            ->with('success', 'Mileage updated successfully. Oil change status has been recalculated.');
    }
}
