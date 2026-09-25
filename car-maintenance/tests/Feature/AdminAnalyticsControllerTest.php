<?php

namespace Tests\Feature;

use App\Models\AnalyticsPageView;
use App\Models\BlogPost;
use App\Models\Car;
use App\Models\OilChange;
use App\Models\RouteAttempt;
use App\Models\Trip;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAnalyticsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect(route('login'));
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_users_without_analytics_permission_are_forbidden(string $role): void
    {
        $user = match ($role) {
            'driver' => User::factory()->create(),
            'trip administrator' => User::factory()->tripAdmin()->create(),
            'blog administrator' => User::factory()->blogAdmin()->create(),
        };

        $this->actingAs($user)
            ->get(route('admin.analytics.index'))
            ->assertForbidden();
    }

    public function test_analytics_administrator_sees_traffic_and_product_metrics_for_selected_period(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        $admin = User::factory()->analyticsAdmin()->create(['created_at' => '2026-01-01 00:00:00']);
        $activatedUser = User::factory()->create(['created_at' => '2026-09-24 09:00:00']);
        User::factory()->create(['created_at' => '2026-09-24 10:00:00']);
        $car = Car::factory()->for($activatedUser)->create(['created_at' => '2026-09-24 11:00:00']);
        OilChange::factory()->for($car)->create(['created_at' => '2026-09-24 12:00:00']);
        $trip = Trip::factory()->for($admin)->create(['created_at' => '2026-01-01 00:00:00']);
        $participant = TripParticipant::factory()->for($trip)->for($activatedUser)->create(['created_at' => '2026-09-24 13:00:00']);
        RouteAttempt::factory()->for($participant, 'participant')->create([
            'status' => 'completed',
            'finished_at' => '2026-09-24 14:00:00',
        ]);
        BlogPost::factory()->for($admin, 'author')->published()->create([
            'published_at' => '2026-09-24 15:00:00',
        ]);
        AnalyticsPageView::factory()->create([
            'session_hash' => hash('sha256', 'session-a'),
            'route_name' => 'home',
            'route_uri' => '/',
            'referrer_host' => 'search.example',
            'occurred_at' => '2026-09-24 08:00:00',
        ]);
        AnalyticsPageView::factory()->create([
            'session_hash' => hash('sha256', 'session-a'),
            'route_name' => 'blog.show',
            'route_uri' => 'blog/{slug}',
            'referrer_host' => 'search.example',
            'occurred_at' => '2026-09-24 08:05:00',
        ]);
        AnalyticsPageView::factory()->create([
            'session_hash' => hash('sha256', 'session-b'),
            'route_name' => 'routes.index',
            'route_uri' => 'routes',
            'occurred_at' => '2026-09-25 08:00:00',
        ]);
        AnalyticsPageView::factory()->create(['occurred_at' => '2026-08-01 08:00:00']);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index', ['period' => '7']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics')
                ->where('auth.user.is_analytics_admin', true)
                ->where('filters.period', '7')
                ->where('filters.from', '2026-09-19')
                ->where('filters.to', '2026-09-25')
                ->where('report.overview.page_views', 3)
                ->where('report.overview.unique_sessions', 2)
                ->where('report.overview.registrations', 2)
                ->where('report.overview.activation_rate', 50)
                ->where('report.product.cars_added', 1)
                ->where('report.product.oil_changes', 1)
                ->where('report.product.route_joins', 1)
                ->where('report.product.completed_attempts', 1)
                ->where('report.product.published_posts', 1)
                ->where('report.product.blog_views', 1)
                ->has('report.trend', 7)
                ->where('report.top_pages.0.route_name', 'blog.show')
                ->where('report.top_pages.0.views', 1)
                ->where('report.top_referrers.0.host', 'search.example')
                ->where('report.top_referrers.0.views', 2));

        $this->assertDatabaseCount('analytics_page_views', 4);
    }

    public function test_custom_range_cannot_exceed_one_year(): void
    {
        $admin = User::factory()->analyticsAdmin()->create();

        $this->actingAs($admin)
            ->from(route('admin.analytics.index'))
            ->get(route('admin.analytics.index', [
                'period' => 'custom',
                'from' => '2025-01-01',
                'to' => '2026-01-02',
            ]))
            ->assertRedirect(route('admin.analytics.index'))
            ->assertSessionHasErrors('to');
    }

    public function test_unknown_preset_is_rejected(): void
    {
        $admin = User::factory()->analyticsAdmin()->create();

        $this->actingAs($admin)
            ->from(route('admin.analytics.index'))
            ->get(route('admin.analytics.index', ['period' => '365']))
            ->assertRedirect(route('admin.analytics.index'))
            ->assertSessionHasErrors('period');
    }

    public function test_custom_range_is_applied_inclusively(): void
    {
        $admin = User::factory()->analyticsAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.analytics.index', [
                'period' => 'custom',
                'from' => '2026-09-01',
                'to' => '2026-09-10',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', 'custom')
                ->where('filters.from', '2026-09-01')
                ->where('filters.to', '2026-09-10')
                ->has('report.trend', 10));
    }

    public function test_analytics_admin_command_grants_and_revokes_access(): void
    {
        $user = User::factory()->create();

        $this->artisan('analytics:admin', ['email' => $user->email])->assertSuccessful();

        $this->assertTrue($user->fresh()->is_analytics_admin);

        $this->artisan('analytics:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();

        $this->assertFalse($user->fresh()->is_analytics_admin);
    }

    public function test_analytics_admin_command_fails_for_unknown_account(): void
    {
        $this->artisan('analytics:admin', ['email' => 'missing@example.com'])
            ->expectsOutput('No account exists with that email. Register first.')
            ->assertFailed();
    }

    public function test_registration_cannot_self_assign_analytics_administration(): void
    {
        $this->post(route('register'), [
            'name' => 'Analytics Intruder',
            'email' => 'analytics-intruder@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'country' => 'PH',
            'is_analytics_admin' => true,
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse(User::where('email', 'analytics-intruder@example.com')->firstOrFail()->is_analytics_admin);
    }

    public static function unprivilegedRoles(): array
    {
        return [
            'driver' => ['driver'],
            'trip administrator' => ['trip administrator'],
            'blog administrator' => ['blog administrator'],
        ];
    }
}
