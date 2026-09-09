<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['status', 'started_at', 'finished_at', 'elapsed_ms'])]
class RouteAttempt extends Model
{
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'elapsed_ms' => 'integer'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(TripParticipant::class, 'trip_participant_id');
    }
}
