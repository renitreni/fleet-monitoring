<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'description', 'route_points', 'checkpoints', 'distance_m', 'invite_token', 'is_public', 'ends_at', 'closed_at'])]
#[Hidden(['invite_token'])]
class Trip extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['route_points' => 'array', 'checkpoints' => 'array', 'is_public' => 'boolean', 'ends_at' => 'datetime', 'closed_at' => 'datetime', 'distance_m' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TripParticipant::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null && $this->ends_at->isFuture();
    }
}
