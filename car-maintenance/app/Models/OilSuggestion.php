<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'car_id',
    'source_hash',
    'suggestions_json',
])]
class OilSuggestion extends Model
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
            'suggestions_json' => 'array',
        ];
    }

    /**
     * Build the deduplication hash for a vehicle specification.
     *
     * Suggestions are cached forever per make/model/year/country combination,
     * so the AI API is called at most once per unique vehicle specification.
     */
    public static function sourceHashFor(Car $car): string
    {
        return hash('sha256', "{$car->make}:{$car->model}:{$car->year}:{$car->country}");
    }

    /**
     * Get the car that owns this suggestion.
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
