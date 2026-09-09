<?php

namespace Tests\Feature;

use App\Models\RouteAttempt;
use App\Models\Trip;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RouteMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attributes = []): Trip
    {
        return Trip::factory()->create([...$attributes, 'is_route' => true, 'is_public' => true, 'ends_at' => null]);
    }

    public function test_catalog_shows_only_published_routes_with_geometry_and_sorts_and_searches(): void
    {
        $short = $this->course(['name' => 'Alpine', 'distance_m' => 200]);
        $long = $this->course(['name' => 'Coastal', 'distance_m' => 500]);
        Trip::factory()->create(['is_public' => true]);
        Trip::factory()->create(['is_route' => true, 'is_public' => false]);
        $this->course(['closed_at' => now()]);
        TripParticipant::factory()->for($short)->create();

        foreach (['shortest' => $short, 'longest' => $long, 'popular' => $short, 'name' => $short, 'newest' => $long] as $sort => $first) {
            $this->get('/routes?sort='.$sort)->assertInertia(fn (Assert $page) => $page->component('Trips/Marketplace')
                ->has('routes.data', 2)->where('routes.data.0.id', $first->id)->has('routes.data.0.route_points', 3)->missing('routes.data.0.invite_token'));
        }
        $this->get('/routes?search=Alpine')->assertInertia(fn (Assert $page) => $page->has('routes.data', 1)->where('routes.data.0.id', $short->id));
        $this->getJson('/routes?sort=invalid')->assertUnprocessable();
        $this->getJson('/routes?filter=invalid')->assertUnprocessable();
        $this->getJson('/routes?search='.str_repeat('a', 121))->assertUnprocessable();
    }

    public function test_join_requires_account_is_idempotent_and_does_not_start_tracking(): void
    {
        $trip = $this->course();
        $this->post(route('routes.join', $trip))->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->post(route('routes.join', $trip))->assertRedirect(route('trips.show', $trip));
        $this->post(route('routes.join', $trip))->assertRedirect(route('trips.show', $trip));
        $this->assertDatabaseCount('trip_participants', 1);
        $this->assertDatabaseCount('route_attempts', 0);
        $this->assertFalse($trip->participants()->first()->tracking);
        $this->assertFalse($trip->participants()->first()->public_consent);
        $this->get('/routes?filter=joined')->assertInertia(fn (Assert $page) => $page->has('routes.data', 1)->where('routes.data.0.joined', true));
        $this->actingAs(User::factory()->create())->get('/routes?filter=joined')->assertInertia(fn (Assert $page) => $page->has('routes.data', 0));
    }

    public function test_guest_sign_in_returns_to_selected_route_without_automatically_joining(): void
    {
        $trip = $this->course();
        $user = User::factory()->create();
        $this->get(route('routes.show', $trip))->assertInertia(fn (Assert $page) => $page->component('Trips/Route')->has('trip.route_points', 3));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('routes.show', $trip));
        $this->assertDatabaseCount('trip_participants', 0);
    }

    public function test_hidden_legacy_and_archived_routes_cannot_be_joined_from_catalog(): void
    {
        $legacy = Trip::factory()->create();
        $hidden = Trip::factory()->create(['is_route' => true]);
        $archived = $this->course(['closed_at' => now()]);
        $this->actingAs(User::factory()->create());
        $this->post(route('routes.join', $legacy))->assertNotFound();
        $this->post(route('routes.join', $hidden))->assertNotFound();
        $this->get(route('routes.show', $hidden))->assertNotFound();
        $this->post(route('routes.join', $archived))->assertConflict();
        $this->assertDatabaseCount('trip_participants', 0);
    }

    public function test_publishing_legacy_course_preserves_private_participants_and_history(): void
    {
        $legacy = Trip::factory()->create();
        $participant = TripParticipant::factory()->for($legacy)->create(['progress_m' => 100]);
        $this->actingAs(User::factory()->create())->patch(route('admin.trips.update', $legacy), ['action' => 'publish'])->assertForbidden();
        $this->actingAs(User::factory()->tripAdmin()->create())->patch(route('admin.trips.update', $legacy), ['action' => 'publish'])->assertRedirect();
        $route = Trip::where('is_route', true)->firstOrFail();
        $this->assertSame($legacy->route_points, $route->route_points);
        $this->assertNull($route->ends_at);
        $this->assertTrue($route->is_public);
        $this->assertSame(0, $route->participants()->count());
        $this->assertSame(100.0, $participant->fresh()->progress_m);
        $this->assertFalse($legacy->fresh()->is_public);
        $this->assertFalse($legacy->fresh()->is_route);
        $this->assertDatabaseCount('route_attempts', 0);
    }

    public function test_publishing_requires_only_course_details_and_creates_a_permanent_route(): void
    {
        $route = Trip::factory()->make();
        $this->actingAs(User::factory()->tripAdmin()->create())->post('/admin/trips', [
            'name' => 'Permanent course', 'route_points' => $route->route_points,
            'checkpoints' => array_map(fn ($checkpoint) => ['name' => $checkpoint['name'], 'point_index' => $checkpoint['point_index']], $route->checkpoints),
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/trips');
        $created = Trip::firstOrFail();
        $this->assertTrue($created->is_route);
        $this->assertTrue($created->is_public);
        $this->assertNull($created->ends_at);
        $this->travel(60)->days();
        $this->assertTrue($created->isOpen());
    }

    public function test_timed_results_use_each_persons_best_share_ties_and_keep_locations_private(): void
    {
        $trip = $this->course();
        $first = TripParticipant::factory()->for($trip)->create(['public_consent' => true, 'last_fix' => ['latitude' => 1, 'longitude' => 2]]);
        $second = TripParticipant::factory()->for($trip)->create(['public_consent' => true]);
        $third = TripParticipant::factory()->for($trip)->create(['public_consent' => true]);
        $private = TripParticipant::factory()->for($trip)->create();
        foreach ([[$first, 20000], [$first, 10000], [$second, 10000], [$third, 30000], [$private, 1000]] as [$participant, $time]) {
            RouteAttempt::factory()->for($participant, 'participant')->create(['status' => 'completed', 'elapsed_ms' => $time, 'started_at' => now()->subMinute(), 'finished_at' => now()]);
        }
        RouteAttempt::factory()->for($third, 'participant')->create(['status' => 'cancelled', 'elapsed_ms' => 1]);
        $this->get(route('routes.show', $trip))->assertInertia(fn (Assert $page) => $page->has('standings', 3)
            ->where('standings.0.elapsed_ms', 10000)->where('standings.0.rank', 1)->where('standings.1.rank', 1)->where('standings.2.rank', 3)
            ->where('standings.2.gap_ms', 20000)->missing('standings.0.location')->missing('standings.0.last_seen_at'));
        $this->get('/routes')->assertInertia(fn (Assert $page) => $page->where('routes.data.0.best_time', 10000));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('publicTrips.0.standings.0.elapsed_ms', 10000)->has('publicTrips.0.route_points', 3));
        $this->actingAs($private->user)->get(route('trips.show', $trip))->assertInertia(fn (Assert $page) => $page->has('standings', 1)->where('standings.0.id', $private->id)->where('personalBest', 1000));
        $this->actingAs($first->user)->patch(route('trips.consent', $trip), ['public_consent' => false])->assertRedirect();
        $this->get(route('routes.show', $trip))->assertInertia(fn (Assert $page) => $page->has('standings', 2));
    }

    public function test_attempt_starts_at_validated_start_includes_pauses_and_can_be_repeated(): void
    {
        $this->freezeTime();
        $trip = $this->course();
        $participant = TripParticipant::factory()->for($trip)->create();
        $this->actingAs($participant->user);
        $token = $this->postJson(route('trips.start', $trip))->assertOk()->json('tracking_token');
        $this->assertNull($participant->attempts()->first()->started_at);
        $this->fix($trip, $token, 0.002)->assertJsonPath('status', 'off_route');
        $this->assertNull($participant->attempts()->first()->started_at);
        $this->fix($trip, $token, 0)->assertJsonPath('status', 'verified');
        $this->postJson(route('trips.stop', $trip), ['tracking_token' => $token])->assertOk();
        $this->travel(60)->seconds();
        $token = $this->postJson(route('trips.start', $trip))->json('tracking_token');
        foreach ([0, 0.001, 0.002, 0.003, 0.004] as $longitude) {
            $this->fix($trip, $token, $longitude)->assertOk();
        }
        $attempt = $participant->attempts()->first();
        $this->assertSame('completed', $attempt->status);
        $this->assertSame(85000, $attempt->elapsed_ms);
        $newToken = $this->postJson(route('trips.start', $trip))->assertOk()->json('tracking_token');
        $this->assertDatabaseCount('route_attempts', 2);
        $this->assertSame(0.0, $participant->fresh()->progress_m);
        $this->assertNull($participant->fresh()->completed_at);
        $this->fix($trip, $token, 0)->assertConflict();
        $this->fix($trip, $newToken, 0)->assertOk();
        $this->assertSame(85000, $attempt->fresh()->elapsed_ms);
    }

    public function test_cancel_and_leave_keep_history_but_invalidate_tracking_tokens(): void
    {
        $trip = $this->course();
        $participant = TripParticipant::factory()->for($trip)->create();
        $this->actingAs(User::factory()->create())->postJson(route('trips.cancel', $trip))->assertNotFound();
        $this->actingAs($participant->user);
        $token = $this->postJson(route('trips.start', $trip))->json('tracking_token');
        $this->postJson(route('trips.cancel', $trip))->assertOk();
        $this->assertSame('cancelled', $participant->attempts()->first()->status);
        $this->fix($trip, $token, 0)->assertConflict();
        $this->postJson(route('trips.start', $trip))->assertOk();
        $this->post(route('trips.leave', $trip))->assertRedirect();
        $this->assertSame(2, $participant->attempts()->where('status', 'cancelled')->count());
        $this->post(route('routes.join', $trip))->assertRedirect();
        $this->assertNull($participant->fresh()->left_at);
        $this->assertDatabaseCount('trip_participants', 1);
    }

    public function test_archiving_stops_active_attempts_preserves_completed_results_and_blocks_new_attempts(): void
    {
        $trip = $this->course();
        $participant = TripParticipant::factory()->for($trip)->create();
        $completed = RouteAttempt::factory()->for($participant, 'participant')->create(['status' => 'completed', 'elapsed_ms' => 30000]);
        $this->actingAs($participant->user)->postJson(route('trips.start', $trip))->assertOk();
        $this->actingAs(User::factory()->tripAdmin()->create())->patch(route('admin.trips.update', $trip), ['action' => 'close'])->assertRedirect();
        $this->assertFalse($participant->fresh()->tracking);
        $this->assertSame(1, $participant->attempts()->where('status', 'cancelled')->count());
        $this->assertSame('completed', $completed->fresh()->status);
        $this->actingAs($participant->user)->postJson(route('trips.start', $trip))->assertConflict();
    }

    public function test_sort_and_filter_survive_pagination(): void
    {
        Trip::factory()->count(13)->create(['is_route' => true, 'is_public' => true, 'ends_at' => null, 'name' => 'Coastal']);
        $this->get('/routes?sort=shortest&search=Coastal')->assertInertia(fn (Assert $page) => $page->has('routes.data', 12)->where('routes.total', 13)->where('routes.next_page_url', fn ($url) => str_contains($url, 'sort=shortest') && str_contains($url, 'search=Coastal')));
        $this->get('/routes?sort=shortest&search=Coastal&page=2')->assertInertia(fn (Assert $page) => $page->has('routes.data', 1));
    }

    private function fix(Trip $trip, string $token, float $longitude): TestResponse
    {
        $this->travel(5)->seconds();

        return $this->postJson(route('trips.location', $trip), ['tracking_token' => $token, 'latitude' => 0, 'longitude' => $longitude, 'accuracy' => 5, 'recorded_at' => now()->toIso8601String()]);
    }
}
