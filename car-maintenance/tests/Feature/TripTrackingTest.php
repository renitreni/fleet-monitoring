<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripParticipant;
use App\Models\User;
use App\Services\TripStandings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TripTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function start(): array
    {
        $this->freezeTime();
        $participant = TripParticipant::factory()->create();
        $token = $this->actingAs($participant->user)->postJson(route('trips.start', $participant->trip))->assertOk()->json('tracking_token');

        return [$participant, $token];
    }

    private function fix(TripParticipant $participant, string $token, float $longitude, array $extra = []): TestResponse
    {
        $this->travel(5)->seconds();

        return $this->postJson(route('trips.location', $participant->trip), [...['tracking_token' => $token, 'latitude' => 0, 'longitude' => $longitude, 'accuracy' => 5, 'speed' => null, 'recorded_at' => now()->toIso8601String()], ...$extra]);
    }

    public function test_progress_requires_start_then_ordered_checkpoints_and_completion_stops_tracking(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0.004)->assertOk();
        $this->assertSame(0, $participant->fresh()->checkpoints_completed);
        foreach ([0, 0.001, 0.002, 0.003, 0.004] as $lng) {
            $this->fix($participant, $token, $lng)->assertOk();
        }
        $participant->refresh();
        $this->assertSame(3, $participant->checkpoints_completed);
        $this->assertNotNull($participant->completed_at);
        $this->assertFalse($participant->tracking);
        $this->assertEqualsWithDelta(444.78, $participant->progress_m, 0.1);
        $this->fix($participant, $token, 0.004)->assertConflict();
    }

    public function test_detours_gps_jumps_and_inaccurate_fixes_do_not_award_progress(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0)->assertJsonPath('status', 'verified');
        $this->fix($participant, $token, 0.001)->assertJsonPath('status', 'verified');
        $before = $participant->fresh()->progress_m;
        $this->fix($participant, $token, 0.002, ['latitude' => 0.002])->assertJsonPath('status', 'off_route');
        $this->fix($participant, $token, 0.003)->assertJsonPath('status', 'off_route');
        $this->assertSame($before, $participant->fresh()->progress_m);
        $this->fix($participant, $token, 0.001)->assertJsonPath('status', 'reanchored');
        $this->fix($participant, $token, 0.002, ['accuracy' => 150])->assertJsonPath('status', 'inaccurate');
        $this->assertSame($before, $participant->fresh()->progress_m);
        $this->assertDatabaseHas('trip_locations', ['accuracy' => 150, 'verified' => false]);
    }

    public function test_old_future_duplicate_and_out_of_order_updates_return_422_without_recording(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0)->assertOk();
        $recorded = now()->toIso8601String();
        foreach ([$recorded, now()->subMinutes(2)->toIso8601String(), now()->addMinutes(2)->toIso8601String()] as $timestamp) {
            $this->fix($participant, $token, 0.001, ['recorded_at' => $timestamp])->assertUnprocessable()->assertJsonValidationErrors('recorded_at');
        }
        $this->assertDatabaseCount('trip_locations', 1);
    }

    public function test_stopping_invalidates_old_session_hides_marker_and_restart_cannot_skip_route(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0)->assertOk();
        $this->postJson(route('trips.stop', $participant->trip))->assertOk();
        $this->assertNull($participant->fresh()->last_fix);
        $this->fix($participant, $token, 0.001)->assertConflict();
        $new = $this->postJson(route('trips.start', $participant->trip))->json('tracking_token');
        $this->fix($participant, $token, 0)->assertConflict();
        $this->fix($participant, $new, 0.002)->assertJsonPath('status', 'off_route');
        $this->assertSame(0.0, $participant->fresh()->progress_m);
    }

    public function test_stale_status_and_reconnect_require_reanchoring_without_credit(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0)->assertOk();
        $this->travel(31)->seconds();
        $rows = (new TripStandings)->rows($participant->trip);
        $this->assertSame('stale', $rows[0]['status']);
        $this->fix($participant, $token, 0.001)->assertJsonPath('status', 'off_route');
        $this->fix($participant, $token, 0)->assertJsonPath('status', 'reanchored');
        $this->assertSame(0.0, $participant->fresh()->progress_m);
    }

    public function test_rankings_ignore_speed_arrival_and_share_tied_ranks(): void
    {
        $trip = Trip::factory()->create();
        TripParticipant::factory()->for($trip)->create(['progress_m' => 100, 'checkpoints_completed' => 1]);
        TripParticipant::factory()->for($trip)->create(['progress_m' => 200, 'checkpoints_completed' => 1]);
        TripParticipant::factory()->for($trip)->create(['progress_m' => 200, 'checkpoints_completed' => 1]);
        $rows = (new TripStandings)->rows($trip);
        $this->assertSame([1, 1, 3], array_column($rows, 'rank'));
    }

    public function test_location_permissions_and_expired_trip_reject_writes(): void
    {
        [$participant, $token] = $this->start();
        $this->actingAs(User::factory()->create());
        $this->fix($participant, $token, 0)->assertNotFound();
        $this->actingAs($participant->user);
        $participant->trip->update(['ends_at' => now()->subSecond()]);
        $this->fix($participant, $token, 0)->assertConflict();
        $this->assertDatabaseCount('trip_locations', 0);
    }

    public function test_retention_removes_old_raw_and_cached_locations_preserving_progress(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0)->assertOk();
        $this->fix($participant, $token, 0.001)->assertOk();
        $before = $participant->fresh()->progress_m;
        $this->travel(25)->hours();
        $this->assertNull((new TripStandings)->rows($participant->trip)[0]['location']);
        $this->artisan('trips:prune-locations')->assertSuccessful();
        $this->assertDatabaseCount('trip_locations', 0);
        $this->assertNull($participant->fresh()->verified_fix);
        $this->assertNull($participant->fresh()->last_fix);
        $this->assertSame($before, $participant->fresh()->progress_m);
    }

    public function test_skipping_an_intermediate_checkpoint_caps_progress_and_does_not_complete(): void
    {
        [$participant, $token] = $this->start();
        foreach ([0, 0.001, 0.003, 0.004] as $lng) {
            $this->fix($participant, $token, $lng)->assertOk();
        }
        $participant->refresh();
        $this->assertSame(1, $participant->checkpoints_completed);
        $this->assertNull($participant->completed_at);
        $this->assertLessThanOrEqual(222.4, $participant->progress_m);
    }

    public function test_corner_cutting_is_rejected_even_when_both_fixes_are_on_route(): void
    {
        [$participant, $token] = $this->start();
        $participant->trip->update([
            'route_points' => [['latitude' => 0, 'longitude' => 0], ['latitude' => 0, 'longitude' => 0.00085], ['latitude' => 0.00085, 'longitude' => 0.00085]],
            'checkpoints' => [['name' => 'Start', 'point_index' => 0, 'distance_m' => 0], ['name' => 'Finish', 'point_index' => 2, 'distance_m' => 189.03]],
            'distance_m' => 189.03,
        ]);
        $this->fix($participant, $token, 0)->assertOk();
        $this->fix($participant, $token, 0.00085, ['latitude' => 0.00085])->assertJsonPath('status', 'detour');
        $this->assertSame(0.0, $participant->fresh()->progress_m);
    }

    public function test_accuracy_circle_must_fit_within_course_and_checkpoint_radius(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0, ['latitude' => 0.0002, 'accuracy' => 30])->assertJsonPath('status', 'off_route');
        $this->assertSame(0, $participant->fresh()->checkpoints_completed);
        $this->fix($participant, $token, 0, ['accuracy' => 30])->assertJsonPath('status', 'verified');
        $this->fix($participant, $token, 0.001, ['accuracy' => 30.1])->assertJsonPath('status', 'inaccurate');
        $this->assertNull($participant->fresh()->last_fix);
        $this->assertNull($participant->fresh()->verified_fix);
    }

    public function test_speed_is_recorded_but_cannot_change_progress_or_completion(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0, ['speed' => 100, 'progress_m' => 444.78, 'checkpoints_completed' => 3])->assertJsonPath('progress', 0);
        $this->assertDatabaseHas('trip_locations', ['speed' => 100]);
        $this->assertSame(1, $participant->fresh()->checkpoints_completed);
        $this->assertNull($participant->fresh()->completed_at);
    }

    public function test_old_tab_stop_cannot_stop_a_new_tracking_session(): void
    {
        [$participant, $token] = $this->start();
        $newToken = $this->postJson(route('trips.start', $participant->trip))->json('tracking_token');
        $this->postJson(route('trips.stop', $participant->trip), ['tracking_token' => $token])->assertOk();
        $this->fix($participant, $newToken, 0)->assertJsonPath('status', 'verified');
        $this->assertTrue($participant->fresh()->tracking);
    }

    public function test_pruning_keeps_fresh_data_while_deleting_expired_data(): void
    {
        [$participant, $token] = $this->start();
        $this->fix($participant, $token, 0)->assertOk();
        $participant->locations()->create(['latitude' => 0, 'longitude' => 0, 'accuracy' => 5, 'recorded_at' => now()->subDays(2), 'received_at' => now()->subHours(24), 'verified' => false]);
        $this->artisan('trips:prune-locations')->assertSuccessful();
        $this->assertDatabaseCount('trip_locations', 1);
        $this->assertNotNull($participant->fresh()->last_fix);
    }
}
