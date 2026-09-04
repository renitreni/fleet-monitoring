<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Append;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'make',
    'model',
    'year',
    'current_mileage',
    'country',
    'vin',
])]
#[Append(['latest_oil_change', 'oil_status'])]
class Car extends Model
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
            'year' => 'integer',
            'current_mileage' => 'integer',
        ];
    }

    /**
     * Get the user that owns the car.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the oil changes for the car.
     */
    public function oilChanges(): HasMany
    {
        return $this->hasMany(OilChange::class);
    }

    /**
     * Get the oil suggestions for the car.
     */
    public function oilSuggestions(): HasMany
    {
        return $this->hasMany(OilSuggestion::class);
    }

    /**
     * Get the latest oil change for the car.
     */
    public function getLatestOilChangeAttribute(): ?OilChange
    {
        return $this->oilChanges()->latest('last_changed_at')->first();
    }

    /**
     * Get the oil status for the car based on the latest oil change.
     *
     * Returns: 'ok' | 'due_soon' | 'overdue' | null
     */
    public function getOilStatusAttribute(): ?string
    {
        $oilChange = $this->latest_oil_change;

        if (! $oilChange) {
            return null;
        }

        if ($oilChange->isDue()) {
            return 'overdue';
        }

        $dueSoonDate = Carbon::parse($oilChange->next_due_date)->subDays(7);
        $dueSoonMileage = $oilChange->next_due_mileage - 500;

        if (now()->gte($dueSoonDate) || $this->current_mileage >= $dueSoonMileage) {
            return 'due_soon';
        }

        return 'ok';
    }
}
