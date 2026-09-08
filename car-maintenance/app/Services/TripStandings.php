<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripParticipant;

class TripStandings
{
    public function rows(Trip $trip, bool $public = false): array
    {
        $participants = $trip->participants()->with('user:id,name')->whereNull('left_at')
            ->when($public, fn ($query) => $query->where('public_consent', true))
            ->get()->sort(fn (TripParticipant $a, TripParticipant $b) => [(int) floor($b->progress_m), $b->checkpoints_completed, -$b->id] <=> [(int) floor($a->progress_m), $a->checkpoints_completed, -$a->id])->values();
        $previousScore = null;
        $rank = 0;

        return $participants->map(function (TripParticipant $participant, int $index) use ($trip, $public, &$previousScore, &$rank) {
            $metres = (int) floor($participant->progress_m);
            $score = [$metres, $participant->checkpoints_completed];
            if ($previousScore !== $score) {
                $rank = $index + 1;
                $previousScore = $score;
            }
            $name = $participant->user?->name ?? 'Deleted account';
            $row = [
                'id' => $participant->id,
                'name' => $name,
                'avatar' => mb_strtoupper(collect(explode(' ', $name))->filter()->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->implode('')),
                'rank' => $rank,
                'progress' => round(min(100, $participant->progress_m / $trip->distance_m * 100), 1),
                'checkpoints_completed' => $participant->checkpoints_completed,
                'completed' => $participant->completed_at !== null,
            ];
            if (! $public) {
                $fresh = $participant->last_received_at?->gt(now()->subSeconds(30)) ?? false;
                $retained = $participant->last_received_at?->gt(now()->subHours(24)) ?? false;
                $row += [
                    'user_id' => $participant->user_id,
                    'status' => ! $trip->isOpen() ? 'ended' : ($participant->completed_at ? 'completed' : (! $participant->tracking ? 'stopped' : (! $fresh ? 'stale' : ($participant->continuous ? 'live' : 'unverified')))),
                    'last_seen_at' => $participant->last_received_at?->toIso8601String(),
                    'location' => $retained && $participant->tracking && $trip->isOpen() ? $participant->last_fix : null,
                ];
            }

            return $row;
        })->all();
    }

    public function publicTrips(): array
    {
        return Trip::query()->where('is_public', true)->with('user:id,name')->latest('id')->limit(6)->get()->map(fn (Trip $trip) => [
            'id' => $trip->id,
            'name' => $trip->name,
            'creator' => $trip->user?->name ?? 'Deleted account',
            'distance_km' => round($trip->distance_m / 1000, 1),
            'checkpoint_count' => count($trip->checkpoints),
            'open' => $trip->isOpen(),
            'standings' => array_slice($this->rows($trip, true), 0, 10),
        ])->all();
    }
}
