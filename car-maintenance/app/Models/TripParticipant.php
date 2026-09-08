<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['trip_id', 'user_id', 'public_consent'])]
#[Hidden(['tracking_token', 'last_fix', 'verified_fix'])]
class TripParticipant extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['public_consent' => 'boolean', 'tracking' => 'boolean', 'continuous' => 'boolean', 'progress_m' => 'float', 'checkpoints_completed' => 'integer', 'last_fix' => 'array', 'verified_fix' => 'array', 'last_received_at' => 'datetime', 'last_recorded_at' => 'datetime', 'completed_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TripLocation::class);
    }
}
