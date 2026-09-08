<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TripsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Sunday coastal drive', 'description' => 'Meet at the start.', 'is_public' => true, 'ends_at' => now()->addDay()->toIso8601String(),
            'route_points' => [['latitude' => 0, 'longitude' => 0], ['latitude' => 0, 'longitude' => 0.002], ['latitude' => 0, 'longitude' => 0.004]],
            'checkpoints' => [['name' => 'Start', 'point_index' => 0], ['name' => 'Meet up', 'point_index' => 1], ['name' => 'Finish', 'point_index' => 2]],
        ];
    }

    public function test_only_admins_can_create_trips_and_server_owns_creator_and_distance(): void
    {
        $this->get('/admin/trips')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post('/admin/trips', $this->payload())->assertForbidden();
        $admin = User::factory()->tripAdmin()->create();
        $this->actingAs($admin)->post('/admin/trips', [...$this->payload(), 'user_id' => 900, 'distance_m' => 1])->assertRedirect('/admin/trips');
        $trip = Trip::firstOrFail();
        $this->assertSame($admin->id, $trip->user_id);
        $this->assertEqualsWithDelta(444.78, $trip->distance_m, 0.1);
        $this->get('/admin/trips')->assertInertia(fn (Assert $page) => $page->component('Trips/Admin')->has('trips.data', 1));
    }

    public function test_invalid_checkpoint_order_missing_endpoints_and_bad_routes_are_rejected(): void
    {
        $this->actingAs(User::factory()->tripAdmin()->create());
        foreach ([[0, 2, 1], [0, 1, 1], [1, 2], [0, 99]] as $indices) {
            $data = $this->payload();
            $data['checkpoints'] = array_map(fn ($index) => ['name' => 'Checkpoint', 'point_index' => $index], $indices);
            $this->post('/admin/trips', $data)->assertSessionHasErrors('checkpoints');
        }
        $data = $this->payload();
        $data['route_points'][1]['latitude'] = 90;
        $this->post('/admin/trips', $data)->assertSessionHasErrors('route_points.1.latitude');
        $data['route_points'][1]['latitude'] = 1;
        $this->post('/admin/trips', $data)->assertSessionHasErrors('route_points');
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_invitation_survives_registration_and_join_is_explicit_and_idempotent(): void
    {
        $trip = Trip::factory()->create();
        $invite = route('trips.invite', $trip->invite_token);
        $this->get($invite)->assertRedirect('/login');
        $this->post('/register', ['name' => 'New driver', 'email' => 'driver@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'country' => 'PH', 'is_trip_admin' => true])->assertRedirect($invite);
        $this->assertFalse(User::where('email', 'driver@example.com')->firstOrFail()->is_trip_admin);
        $this->get($invite)->assertInertia(fn (Assert $page) => $page->component('Trips/Join')->where('trip.name', $trip->name)->missing('trip.route_points'));
        $this->assertDatabaseCount('trip_participants', 0);
        $this->post($invite, ['public_consent' => false])->assertRedirect(route('trips.show', $trip));
        $this->post($invite, ['public_consent' => false])->assertRedirect(route('trips.show', $trip));
        $this->assertDatabaseCount('trip_participants', 1);
    }

    public function test_only_participants_can_read_trip_locations_including_admins(): void
    {
        $trip = Trip::factory()->create();
        $outsider = User::factory()->tripAdmin()->create();
        $this->actingAs($outsider)->get(route('trips.show', $trip))->assertNotFound();
        $this->postJson(route('trips.start', $trip))->assertNotFound();
        $participant = TripParticipant::factory()->for($trip)->create();
        $this->actingAs($participant->user)->get(route('trips.show', $trip))->assertInertia(fn (Assert $page) => $page->component('Trips/Show')->missing('trip.invite_token')->missing('participant.tracking_token'));
    }

    public function test_landing_page_shows_only_consented_summaries_and_creator_without_gps_or_tokens(): void
    {
        $trip = Trip::factory()->create(['is_public' => true]);
        Trip::factory()->create(['name' => 'Private route']);
        TripParticipant::factory()->for($trip)->create(['public_consent' => false]);
        TripParticipant::factory()->for($trip)->create(['public_consent' => true, 'last_fix' => ['latitude' => 42, 'longitude' => 19]]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Welcome')->has('publicTrips', 1)->where('publicTrips.0.creator', $trip->user->name)->has('publicTrips.0.standings', 1)->missing('publicTrips.0.route_points')->missing('publicTrips.0.invite_token')->missing('publicTrips.0.standings.0.location')->missing('publicTrips.0.standings.0.last_seen_at'));
    }

    public function test_admin_can_rotate_invite_hide_and_close_trip_but_cannot_change_course(): void
    {
        $trip = Trip::factory()->create(['is_public' => true]);
        $oldToken = $trip->invite_token;
        $this->actingAs(User::factory()->create())->patch(route('admin.trips.update', $trip), ['action' => 'close'])->assertForbidden();
        $this->actingAs(User::factory()->tripAdmin()->create())->patch(route('admin.trips.update', $trip), ['action' => 'rotate'])->assertRedirect();
        $this->get(route('trips.invite', $oldToken))->assertNotFound();
        $this->patch(route('admin.trips.update', $trip), ['action' => 'visibility', 'is_public' => false, 'route_points' => []])->assertRedirect();
        $this->assertFalse($trip->fresh()->is_public);
        $this->assertCount(3, $trip->fresh()->route_points);
        $this->patch(route('admin.trips.update', $trip), ['action' => 'close'])->assertRedirect();
        $this->post(route('trips.join', $trip->fresh()->invite_token), ['public_consent' => false])->assertGone();
    }

    public function test_leave_purges_locations_revokes_access_and_removes_public_consent(): void
    {
        $participant = TripParticipant::factory()->create(['public_consent' => true, 'tracking' => true]);
        $participant->locations()->create(['latitude' => 0, 'longitude' => 0, 'accuracy' => 5, 'recorded_at' => now(), 'received_at' => now(), 'verified' => true]);
        $this->actingAs($participant->user)->post(route('trips.leave', $participant->trip))->assertRedirect('/trips');
        $this->assertDatabaseCount('trip_locations', 0);
        $this->assertFalse($participant->fresh()->tracking);
        $this->assertFalse($participant->fresh()->public_consent);
        $this->get(route('trips.show', $participant->trip))->assertNotFound();
        $this->postJson(route('trips.start', $participant->trip))->assertNotFound();
    }

    public function test_admin_command_requires_existing_account_and_can_revoke_access(): void
    {
        $user = User::factory()->create();
        $this->artisan('trips:admin', ['email' => 'missing@example.com'])->assertFailed();
        $this->artisan('trips:admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_trip_admin);
        $this->artisan('trips:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_trip_admin);
    }

    public function test_public_consent_can_be_withdrawn_and_cannot_be_changed_by_an_outsider(): void
    {
        $participant = TripParticipant::factory()->create(['public_consent' => true]);
        $trip = $participant->trip;
        $this->actingAs(User::factory()->create())->patch(route('trips.consent', $trip), ['public_consent' => false])->assertNotFound();
        $this->actingAs($participant->user)->patch(route('trips.consent', $trip), ['public_consent' => false])->assertRedirect();
        $this->assertFalse($participant->fresh()->public_consent);
    }

    public function test_trip_permissions_cover_members_nonmembers_admins_and_departed_members(): void
    {
        $trip = Trip::factory()->create();
        $member = TripParticipant::factory()->for($trip)->create();
        $departed = TripParticipant::factory()->for($trip)->create(['left_at' => now()]);
        $admin = User::factory()->tripAdmin()->create();
        $outsider = User::factory()->create();
        $this->assertTrue($member->user->can('view', $trip));
        $this->assertFalse($departed->user->can('view', $trip));
        $this->assertFalse($admin->can('view', $trip));
        $this->assertFalse($outsider->can('view', $trip));
        $this->assertTrue($admin->can('create', Trip::class));
        $this->assertTrue($admin->can('update', $trip));
        $this->assertFalse($outsider->can('create', Trip::class));
        $this->assertFalse($member->user->can('update', $trip));
    }

    public function test_login_resumes_invitation_and_trip_responses_are_not_cached(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $invite = route('trips.invite', $trip->invite_token);
        $this->get($invite)->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($invite);
        $response = $this->get($invite)->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->post($invite, ['public_consent' => false]);
        $this->get(route('trips.show', $trip))->assertInertia(fn (Assert $page) => $page->where('participant.resume_point.latitude', 0)->where('participant.resume_point.longitude', 0));
    }
}
