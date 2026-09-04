<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'car_id',
    'last_changed_at',
    'last_changed_mileage',
    'interval_months',
    'interval_mileage',
    'next_due_date',
    'next_due_mileage',
])]
class OilChange extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_changed_at' => 'date',
            'next_due_date' => 'date',
            'last_changed_mileage' => 'integer',
            'interval_months' => 'integer',
            'interval_mileage' => 'integer',
            'next_due_mileage' => 'integer',
        ];
    }

    /**
     * Get the car that owns this oil change.
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Compute the next due date and next due mileage based on
     * the last change and configured intervals.
     */
    public function computeNextDue(): void
    {
        $this->next_due_date = Carbon::parse($this->last_changed_at)
            ->addMonths($this->interval_months);

        $this->next_due_mileage = $this->last_changed_mileage + $this->interval_mileage;
    }

    /**
     * Determine if this oil change is overdue.
     *
     * An oil change is due if the due date has passed or the car's
     * current mileage has reached/exceeded the due mileage.
     */
    public function isDue(): bool
    {
        $car = $this->car;

        $dateOverdue = $this->next_due_date && $this->next_due_date->isPast();
        $mileageOverdue = $car && $this->next_due_mileage && $car->current_mileage >= $this->next_due_mileage;

        return $dateOverdue || $mileageOverdue;
    }
}
