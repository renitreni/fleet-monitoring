<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripLocationRequest;
use App\Models\Trip;
use App\Models\TripParticipant;
use App\Services\RouteProgress;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TripTrackingController extends Controller
{
    public function start(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        $token = DB::transaction(function () use ($trip, $request) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            abort_unless($trip->isOpen(), 409, 'This trip has ended.');
            $participant = $this->participant($trip, $request);
            if ($trip->is_route) {
                $active = $participant->attempts()->whereIn('status', ['ready', 'active'])->latest('id')->first();
                if (! $active) {
                    $participant->attempts()->create(['status' => 'ready']);
                    $participant->forceFill(['progress_m' => 0, 'checkpoints_completed' => 0, 'completed_at' => null, 'last_recorded_at' => null]);
                }
            } else {
                abort_if($participant->completed_at, 409, 'You have already completed this trip.');
            }
            $token = (string) Str::uuid();
            $participant->forceFill(['tracking' => true, 'tracking_token' => $token, 'continuous' => false, 'last_fix' => null, 'verified_fix' => null, 'last_received_at' => null])->save();

            return $token;
        });

        return response()->json(['tracking_token' => $token]);
    }

    public function stop(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        DB::transaction(function () use ($trip, $request) {
            $participant = $this->participant($trip, $request);
            if ($request->filled('tracking_token') && $participant->tracking_token !== $request->input('tracking_token')) {
                return;
            }
            $participant->forceFill(['tracking' => false, 'tracking_token' => null, 'continuous' => false, 'last_fix' => null, 'verified_fix' => null])->save();
        });

        return response()->json(['stopped' => true]);
    }

    public function store(StoreTripLocationRequest $request, Trip $trip, RouteProgress $progress): JsonResponse
    {
        $data = $request->validated();
        $result = DB::transaction(function () use ($request, $trip, $data, $progress) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            abort_unless($trip->isOpen(), 409, 'This trip has ended.');
            $participant = $this->participant($trip, $request);
            abort_unless($participant->tracking && $participant->tracking_token && hash_equals($participant->tracking_token, $data['tracking_token']), 409, 'Tracking has stopped or this tracking session was replaced.');
            $recorded = Carbon::parse($data['recorded_at'])->utc();
            if ($recorded->lt(now()->subSeconds(30)) || $recorded->gt(now()->addSeconds(5)) || ($participant->last_recorded_at && $recorded->lte($participant->last_recorded_at))) {
                throw ValidationException::withMessages(['recorded_at' => 'Use a fresh GPS fix. Old, duplicate, out-of-order, or future updates are not accepted.']);
            }
            $fix = [
                'latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude'],
                'accuracy' => (float) $data['accuracy'], 'speed' => isset($data['speed']) ? (float) $data['speed'] : null,
                'recorded_at' => $recorded->toIso8601String(),
            ];
            $attempt = $trip->is_route ? $participant->attempts()->whereIn('status', ['ready', 'active'])->latest('id')->first() : null;
            abort_if($trip->is_route && ! $attempt, 409, 'Start a new attempt first.');
            $status = $progress->advance($trip, $participant, $fix);
            if ($attempt) {
                if (! $attempt->started_at && $participant->checkpoints_completed > 0) {
                    $attempt->started_at = now();
                    $attempt->status = 'active';
                }
                if ($participant->completed_at) {
                    $attempt->finished_at = now();
                    $attempt->elapsed_ms = max(1, (int) $attempt->started_at->diffInMilliseconds($attempt->finished_at));
                    $attempt->status = 'completed';
                }
                $attempt->save();
            }
            $participant->last_recorded_at = $recorded;
            $participant->last_received_at = now();
            // Poor accuracy is recorded for diagnostics but never replaces a usable map marker.
            $participant->last_fix = $fix['accuracy'] <= RouteProgress::MAX_ACCURACY_M ? $fix : null;
            if (! $participant->continuous) {
                $participant->verified_fix = null;
            }
            $participant->save();
            $participant->locations()->create([...$fix, 'recorded_at' => $recorded, 'received_at' => now(), 'verified' => $status === 'verified']);

            return ['status' => $status, 'progress' => round($participant->progress_m / $trip->distance_m * 100, 1), 'completed' => $participant->completed_at !== null];
        });

        return response()->json($result);
    }

    public function cancel(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        abort_unless($trip->is_route, 404);
        DB::transaction(function () use ($request, $trip) {
            Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $participant = $this->participant($trip, $request);
            $participant->attempts()->whereIn('status', ['ready', 'active'])->update(['status' => 'cancelled']);
            $participant->forceFill(['tracking' => false, 'tracking_token' => null, 'continuous' => false, 'last_fix' => null, 'verified_fix' => null])->save();
        });

        return response()->json(['cancelled' => true]);
    }

    private function participant(Trip $trip, Request $request): TripParticipant
    {
        return $trip->participants()->where('user_id', $request->user()->id)->whereNull('left_at')->lockForUpdate()->firstOrFail();
    }
}
