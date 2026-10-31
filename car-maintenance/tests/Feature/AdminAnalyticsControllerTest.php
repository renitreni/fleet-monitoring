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
                ->where('report.top_referrers.0.views', 2)
                ->has('report.registered_users', 2)
                ->where('report.registered_users.0.created_at', '2026-09-24T10:00:00+00:00')
                ->where('report.registered_users.0.has_car', false)
                ->where('report.registered_users.1.created_at', '2026-09-24T09:00:00+00:00')
                ->where('report.registered_users.1.has_car', true));

        $this->assertDatabaseCount('analytics_page_views', 4);
    }

    public function test_top_countries_counts_views_limits_ranking_and_separates_unknowns(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        $admin = User::factory()->analyticsAdmin()->create();
        AnalyticsPageView::factory()->count(3)->create([
            'country_code' => 'PH', 'session_hash' => hash('sha256', 'same-session'),
            'occurred_at' => '2026-10-01 09:00:00',
        ]);
        foreach (['AU', 'CA', 'DE', 'FR', 'GB', 'JP', 'SG', 'US'] as $country) {
            AnalyticsPageView::factory()->create(['country_code' => $country, 'occurred_at' => '2026-10-01 09:00:00']);
        }
        AnalyticsPageView::factory()->count(2)->create(['country_code' => null, 'occurred_at' => '2026-10-01 09:00:00']);
        AnalyticsPageView::factory()->count(4)->create(['country_code' => 'US', 'occurred_at' => '2026-09-01 09:00:00']);
        AnalyticsPageView::factory()->create(['country_code' => null, 'occurred_at' => '2026-09-01 09:00:00']);

        $this->actingAs($admin)->get(route('admin.analytics.index', ['period' => '7']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.top_countries', [
                    ['country_code' => 'PH', 'views' => 3],
                    ['country_code' => 'AU', 'views' => 1],
                    ['country_code' => 'CA', 'views' => 1],
                    ['country_code' => 'DE', 'views' => 1],
                    ['country_code' => 'FR', 'views' => 1],
                    ['country_code' => 'GB', 'views' => 1],
                    ['country_code' => 'JP', 'views' => 1],
                    ['country_code' => 'SG', 'views' => 1],
                ])
                ->where('report.unknown_country_views', 2));
    }

    public function test_country_report_is_empty_when_no_views_exist(): void
    {
        $admin = User::factory()->analyticsAdmin()->create();

        $this->actingAs($admin)->get(route('admin.analytics.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.top_countries', [])
                ->where('report.unknown_country_views', 0));
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

    public function test_administrator_downloads_ad_placement_csv_for_selected_period(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        $admin = User::factory()->analyticsAdmin()->create();
        AnalyticsPageView::factory()->count(3)->create([
            'session_hash' => hash('sha256', 'session-a'),
            'route_name' => 'blog.show',
            'route_uri' => 'blog/{slug}',
            'referrer_host' => 'search.example',
            'country_code' => 'PH',
            'occurred_at' => '2026-09-24 08:00:00',
        ]);
        AnalyticsPageView::factory()->create([
            'session_hash' => hash('sha256', 'session-b'),
            'route_name' => 'home',
            'route_uri' => '/',
            'referrer_host' => 'search.example',
            'country_code' => 'PH',
            'occurred_at' => '2026-09-24 09:00:00',
        ]);
        AnalyticsPageView::factory()->create([
            'route_name' => 'dashboard',
            'route_uri' => 'dashboard',
            'occurred_at' => '2026-09-24 10:00:00',
        ]);
        AnalyticsPageView::factory()->create([
            'route_name' => 'home',
            'route_uri' => '/',
            'occurred_at' => '2026-08-01 08:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.analytics.export', ['period' => '7']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'ad-placement-report_2026-09-19_2026-09-25.csv',
            $response->headers->get('content-disposition'),
        );

        $csv = array_values(array_filter(array_map('str_getcsv', explode("\n", trim($response->streamedContent())))));

        $this->assertContains(['Question answered', 'Where should we put the ads to monetize the system?'], $csv);
        $this->assertContains(['SECTION 1 - Ad placement opportunities (where to place ads)'], $csv);
        $this->assertContains(['Rank', 'Route URI', 'Route name', 'Page views', 'Share of traffic %', 'Unique sessions', 'Views per session', 'Suggested ad format', 'Why'], $csv);
        $this->assertContains(['SECTION 2 - Acquisition channels (who brings the traffic worth monetizing)'], $csv);
        $this->assertContains(['SECTION 3 - Audience geography (geo-target the ad inventory)'], $csv);

        $this->assertContains(['1', 'blog/{slug}', 'blog.show', '3', '60', '1', '3', 'In-content + sidebar', 'Content pages hold attention; ads blend into the reading flow without touching product workflows.'], $csv);
        $this->assertContains(['2', 'dashboard', 'dashboard', '1', '20', '1', '1', 'Keep ad-free', 'Product and account surfaces drive activation and retention; ads here would cost more than they earn.'], $csv);
        $this->assertContains(['3', '/', 'home', '1', '20', '1', '1', 'Below-the-fold banner', 'Landing attention is high; place ads after the value proposition to protect conversion.'], $csv);

        $this->assertContains(['1', 'search.example', '4', '2', '80'], $csv);
        $this->assertContains(['1', 'PH', '4', '80'], $csv);
        $this->assertNotContains(['1', '/', 'home', '2', '50', '2', '1', 'Below-the-fold banner', 'Landing attention is high; place ads after the value proposition to protect conversion.'], $csv);
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_users_without_analytics_permission_cannot_export_ad_placement_csv(string $role): void
    {
        $user = match ($role) {
            'driver' => User::factory()->create(),
            'trip administrator' => User::factory()->tripAdmin()->create(),
            'blog administrator' => User::factory()->blogAdmin()->create(),
        };

        $this->actingAs($user)
            ->get(route('admin.analytics.export'))
            ->assertForbidden();
    }

    public function test_ad_placement_csv_respects_custom_range(): void
    {
        $admin = User::factory()->analyticsAdmin()->create();
        AnalyticsPageView::factory()->create([
            'route_name' => 'home',
            'route_uri' => '/',
            'occurred_at' => '2026-09-05 08:00:00',
        ]);
        AnalyticsPageView::factory()->create([
            'route_name' => 'blog.show',
            'route_uri' => 'blog/{slug}',
            'occurred_at' => '2026-09-15 08:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.analytics.export', [
                'period' => 'custom',
                'from' => '2026-09-01',
                'to' => '2026-09-10',
            ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'ad-placement-report_2026-09-01_2026-09-10.csv',
            $response->headers->get('content-disposition'),
        );

        $body = $response->streamedContent();
        $this->assertStringContainsString('home', $body);
        $this->assertStringNotContainsString('blog/{slug}', $body);
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
