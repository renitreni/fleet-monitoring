<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use App\Services\AnalyticsReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AnalyticsReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registered_users_are_included_when_created_within_range(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        $user1 = User::factory()->create(['created_at' => '2026-09-15 10:00:00']);
        $user2 = User::factory()->create(['created_at' => '2026-09-20 14:30:00']);
        User::factory()->create(['created_at' => '2026-08-15 10:00:00']); // outside range

        $report = (new AnalyticsReport())->forRange($from, $to);

        $this->assertCount(2, $report['registered_users']);
        $this->assertEquals($user1->id, $report['registered_users'][1]['id']); // ordered desc
        $this->assertEquals($user2->id, $report['registered_users'][0]['id']);
        $this->assertEquals(2, $report['overview']['registrations']);
    }

    public function test_registered_users_excludes_users_created_outside_range(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        User::factory()->create(['created_at' => '2026-08-31 23:59:59']);
        User::factory()->create(['created_at' => '2026-10-01 00:00:01']);

        $report = (new AnalyticsReport())->forRange($from, $to);

        $this->assertEmpty($report['registered_users']);
        $this->assertEquals(0, $report['overview']['registrations']);
    }

    public function test_has_car_is_true_when_user_has_car_created_before_range_end(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        $user = User::factory()->create(['created_at' => '2026-09-15 10:00:00']);
        Car::factory()->create([
            'user_id' => $user->id,
            'created_at' => '2026-09-20 12:00:00', // within range
        ]);

        $report = (new AnalyticsReport())->forRange($from, $to);

        $this->assertTrue($report['registered_users'][0]['has_car']);
    }

    public function test_has_car_is_false_when_user_has_no_car(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        $user = User::factory()->create(['created_at' => '2026-09-15 10:00:00']);

        $report = (new AnalyticsReport())->forRange($from, $to);

        $this->assertFalse($report['registered_users'][0]['has_car']);
    }

    public function test_has_car_is_false_when_car_created_after_range_end(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        $user = User::factory()->create(['created_at' => '2026-09-15 10:00:00']);
        Car::factory()->create([
            'user_id' => $user->id,
            'created_at' => '2026-10-05 12:00:00', // after range end
        ]);

        $report = (new AnalyticsReport())->forRange($from, $to);

        $this->assertFalse($report['registered_users'][0]['has_car']);
    }

    public function test_created_at_is_iso8601_string(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        $user = User::factory()->create(['created_at' => '2026-09-15 10:30:45']);

        $report = (new AnalyticsReport())->forRange($from, $to);

        $createdAt = $report['registered_users'][0]['created_at'];
        $this->assertIsString($createdAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $createdAt);
        $this->assertEquals('2026-09-15T10:30:45+00:00', $createdAt);
    }

    public function test_empty_range_returns_empty_registered_users(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        $report = (new AnalyticsReport())->forRange($from, $to);

        $this->assertEmpty($report['registered_users']);
        $this->assertEquals(0, $report['overview']['registrations']);
    }

    public function test_registrations_count_matches_registered_users_count(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        User::factory()->count(5)->create(['created_at' => '2026-09-15 10:00:00']);

        $report = (new AnalyticsReport())->forRange($from, $to);

        $this->assertEquals(count($report['registered_users']), $report['overview']['registrations']);
        $this->assertEquals(5, $report['overview']['registrations']);
    }

    public function test_users_with_trashed_are_included(): void
    {
        $from = CarbonImmutable::parse('2026-09-01 00:00:00');
        $to = CarbonImmutable::parse('2026-09-30 23:59:59');

        $user = User::factory()->create(['created_at' => '2026-09-15 10:00:00']);
        $user->delete(); // soft delete

        $report = (new AnalyticsReport())->forRange($from, $to);

        $this->assertCount(1, $report['registered_users']);
        $this->assertEquals($user->id, $report['registered_users'][0]['id']);
    }
}