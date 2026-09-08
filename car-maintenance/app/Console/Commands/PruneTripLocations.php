<?php

namespace App\Console\Commands;

use App\Models\TripLocation;
use App\Models\TripParticipant;
use Illuminate\Console\Command;

class PruneTripLocations extends Command
{
    protected $signature = 'trips:prune-locations';
    protected $description = 'Delete trip GPS data older than 24 hours and clear expired tracking sessions';

    public function handle(): int
    {
        $cutoff = now()->subHours(24);
        $deleted = TripLocation::where('received_at', '<=', $cutoff)->delete();
        TripParticipant::where('last_received_at', '<=', $cutoff)->update(['last_fix' => null, 'verified_fix' => null, 'last_received_at' => null, 'last_recorded_at' => null, 'continuous' => false]);
        TripParticipant::whereHas('trip', fn ($query) => $query->where('ends_at', '<=', now())->orWhereNotNull('closed_at'))
            ->update(['tracking' => false, 'tracking_token' => null, 'last_fix' => null, 'verified_fix' => null, 'continuous' => false]);
        $this->info("Deleted {$deleted} expired GPS records.");

        return self::SUCCESS;
    }
}
