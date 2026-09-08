<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripParticipant;
use Carbon\Carbon;

class RouteProgress
{
    public const CORRIDOR_M = 40;
    public const MAX_ACCURACY_M = 30;
    public const CHECKPOINT_RADIUS_M = 40;

    /** @param array{latitude: float, longitude: float} $a
     * @param  array{latitude: float, longitude: float}  $b
     */
    public function distance(array $a, array $b): float
    {
        $lat = deg2rad($b['latitude'] - $a['latitude']);
        $lng = deg2rad($b['longitude'] - $a['longitude']);
        $h = sin($lat / 2) ** 2 + cos(deg2rad($a['latitude'])) * cos(deg2rad($b['latitude'])) * sin($lng / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, $h)));
    }

    public function distances(array $points): array
    {
        $distances = [0.0];
        for ($i = 1; $i < count($points); $i++) {
            $distances[] = $distances[$i - 1] + $this->distance($points[$i - 1], $points[$i]);
        }

        return $distances;
    }

    public function pointAt(array $points, float $progress): array
    {
        $distances = $this->distances($points);
        for ($i = 1; $i < count($points); $i++) {
            if ($distances[$i] >= $progress) {
                $length = $distances[$i] - $distances[$i - 1];
                $ratio = $length > 0 ? max(0, min(1, ($progress - $distances[$i - 1]) / $length)) : 0;

                return ['latitude' => $points[$i - 1]['latitude'] + ($points[$i]['latitude'] - $points[$i - 1]['latitude']) * $ratio, 'longitude' => $points[$i - 1]['longitude'] + ($points[$i]['longitude'] - $points[$i - 1]['longitude']) * $ratio];
            }
        }

        return $points[array_key_last($points)];
    }

    /** Project only onto the contiguous, reachable portion of the course. */
    public function project(array $points, array $fix, float $minimum = 0, float $maximum = INF): ?array
    {
        $distances = $this->distances($points);
        $best = null;
        $scale = cos(deg2rad($fix['latitude']));
        for ($i = 1; $i < count($points); $i++) {
            $a = $points[$i - 1];
            $b = $points[$i];
            $x = ($b['longitude'] - $a['longitude']) * $scale;
            $y = $b['latitude'] - $a['latitude'];
            $length = $x * $x + $y * $y;
            if ($length === 0.0) {
                continue;
            }
            $t = max(0, min(1, (($fix['longitude'] - $a['longitude']) * $scale * $x + ($fix['latitude'] - $a['latitude']) * $y) / $length));
            $progress = $distances[$i - 1] + $t * ($distances[$i] - $distances[$i - 1]);
            if ($progress < $minimum || $progress > $maximum) {
                continue;
            }
            $offset = $this->distance($fix, ['latitude' => $a['latitude'] + $t * $y, 'longitude' => $a['longitude'] + $t * ($b['longitude'] - $a['longitude'])]);
            if ($best === null || $offset < $best['offset'] - 1) {
                $best = ['progress' => $progress, 'offset' => $offset];
            }
        }

        return $best;
    }

    /** Mutates only server-owned progress; speed is never a scoring input. */
    public function advance(Trip $trip, TripParticipant $participant, array $fix): string
    {
        if ($fix['accuracy'] > self::MAX_ACCURACY_M) {
            $participant->continuous = false;

            return 'inaccurate';
        }
        $points = $trip->route_points;
        $previous = $participant->verified_fix;
        $elapsed = $previous ? Carbon::parse($previous['recorded_at'])->diffInSeconds(Carbon::parse($fix['recorded_at']), false) : 0;
        $continuous = $participant->continuous && $previous && $elapsed > 0 && $elapsed <= 30;
        $allowance = $continuous ? min(200, $elapsed * 55 + 10) : 0;
        $projection = $this->project($points, $fix, max(0, $participant->progress_m - 40), $participant->progress_m + max(40, $allowance));
        if (! $projection || $projection['offset'] + $fix['accuracy'] > self::CORRIDOR_M) {
            $participant->continuous = false;

            return 'off_route';
        }
        if ($participant->checkpoints_completed === 0) {
            if ($this->distance($points[0], $fix) + $fix['accuracy'] > self::CHECKPOINT_RADIUS_M) {
                return 'return_to_start';
            }
            $participant->checkpoints_completed = 1;
            $participant->verified_fix = $fix;
            $participant->continuous = true;

            return 'verified';
        }
        if (! $continuous) {
            // Re-anchor without awarding unobserved distance (including repeated restarts).
            if (abs($projection['progress'] - $participant->progress_m) > 20) {
                $participant->continuous = false;

                return 'resume_at_progress';
            }
            $participant->verified_fix = $fix;
            $participant->continuous = true;

            return 'reanchored';
        }
        if ($this->distance($previous, $fix) > $allowance + 20) {
            $participant->continuous = false;

            return 'jump';
        }
        // Check the observed chord against the course, rejecting corner-cutting across bends.
        for ($t = 0.1; $t < 1; $t += 0.1) {
            $sample = ['latitude' => $previous['latitude'] + ($fix['latitude'] - $previous['latitude']) * $t, 'longitude' => $previous['longitude'] + ($fix['longitude'] - $previous['longitude']) * $t];
            $path = $this->project($points, $sample, max(0, $participant->progress_m - 40), $participant->progress_m + $allowance);
            if (! $path || $path['offset'] > self::CORRIDOR_M) {
                $participant->continuous = false;

                return 'detour';
            }
        }
        $checkpoint = $trip->checkpoints[$participant->checkpoints_completed];
        $nextDistance = $checkpoint['distance_m'];
        $participant->progress_m = max($participant->progress_m, min($projection['progress'], $nextDistance));
        if (abs($projection['progress'] - $nextDistance) <= self::CHECKPOINT_RADIUS_M && $this->distance($points[$checkpoint['point_index']], $fix) + $fix['accuracy'] <= self::CHECKPOINT_RADIUS_M) {
            $participant->checkpoints_completed++;
            if ($participant->checkpoints_completed === count($trip->checkpoints)) {
                $participant->progress_m = $trip->distance_m;
                $participant->completed_at = now();
                $participant->tracking = false;
                $participant->tracking_token = null;
            }
        }
        $participant->verified_fix = $fix;
        $participant->continuous = true;

        return 'verified';
    }
}
