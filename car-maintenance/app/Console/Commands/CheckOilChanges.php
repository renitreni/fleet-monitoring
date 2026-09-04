<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Models\NotificationLog;
use App\Models\OilChange;
use App\Notifications\OilChangeDueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOilChanges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oil-changes:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all cars for overdue or due-soon oil changes and notify owners.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cars = Car::with(['user', 'oilChanges' => function ($query) {
            $query->latest('last_changed_at');
        }])->get();

        $sentCount = 0;

        foreach ($cars as $car) {
            $latestOilChange = $car->oilChanges->first();

            if (! $latestOilChange) {
                continue;
            }

            $status = $this->determineStatus($car, $latestOilChange);

            if ($status === null) {
                continue;
            }

            if ($this->alreadyNotifiedToday($car, $status)) {
                continue;
            }

            $car->user->notify(new OilChangeDueNotification($car, $latestOilChange, $status));

            NotificationLog::create([
                'notifiable_id' => $car->user_id,
                'notifiable_type' => get_class($car->user),
                'notification_type' => $status,
                'data' => [
                    'car_id' => $car->id,
                    'car_make' => $car->make,
                    'car_model' => $car->model,
                    'next_due_date' => $latestOilChange->next_due_date->format('M j, Y'),
                    'next_due_mileage' => number_format($latestOilChange->next_due_mileage),
                    'current_mileage' => number_format($car->current_mileage),
                ],
                'sent_at' => now(),
            ]);

            $sentCount++;

            Log::info('Oil change notification sent', [
                'user_id' => $car->user_id,
                'car_id' => $car->id,
                'type' => $status,
            ]);
        }

        $this->info("{$sentCount} oil change notification(s) sent.");

        return self::SUCCESS;
    }

    /**
     * Determine the notification status for the car, or null if not due.
     */
    private function determineStatus(Car $car, OilChange $oilChange): ?string
    {
        $dateOverdue = $oilChange->next_due_date && $oilChange->next_due_date->isPast();
        $mileageOverdue = $car->current_mileage >= $oilChange->next_due_mileage;

        if ($dateOverdue || $mileageOverdue) {
            return 'oil_change_overdue';
        }

        $dueSoonDate = Carbon::parse($oilChange->next_due_date)->subDays(7);
        $dueSoonMileage = $oilChange->next_due_mileage - 500;

        if (now()->gte($dueSoonDate) || $car->current_mileage >= $dueSoonMileage) {
            return 'oil_change_due_soon';
        }

        return null;
    }

    /**
     * Check whether a notification of this type has already been sent today for this car.
     */
    private function alreadyNotifiedToday(Car $car, string $type): bool
    {
        return NotificationLog::where('notifiable_id', $car->user_id)
            ->where('notifiable_type', get_class($car->user))
            ->where('notification_type', $type)
            ->where('data->car_id', $car->id)
            ->whereDate('sent_at', today())
            ->exists();
    }
}
