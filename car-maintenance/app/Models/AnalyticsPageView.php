<?php

namespace App\Models;

use Database\Factories\AnalyticsPageViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'session_hash', 'route_name', 'route_uri', 'referrer_host', 'occurred_at'])]
class AnalyticsPageView extends Model
{
    /** @use HasFactory<AnalyticsPageViewFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
