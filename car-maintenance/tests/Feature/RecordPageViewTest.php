<?php

namespace Tests\Feature;

use App\Models\AnalyticsPageView;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecordPageViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_page_view_stores_only_normalized_request_details(): void
    {
        $this->withHeaders([
            'Referer' => 'https://Search.Example/results?q=private',
            'User-Agent' => 'Mozilla/5.0',
        ])->get('/blog?campaign=secret')->assertOk();

        $pageView = AnalyticsPageView::query()->sole();

        $this->assertSame('blog.index', $pageView->route_name);
        $this->assertSame('blog', $pageView->route_uri);
        $this->assertSame('search.example', $pageView->referrer_host);
        $this->assertSame(64, strlen($pageView->session_hash));
        $this->assertNull($pageView->user_id);
        $this->assertStringNotContainsString('secret', implode('|', $pageView->getAttributes()));
    }

    public function test_authenticated_page_view_records_user_without_internal_referrer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['Referer' => url('/cars')])
            ->get(route('dashboard'))
            ->assertOk();

        $pageView = AnalyticsPageView::query()->sole();

        $this->assertSame($user->id, $pageView->user_id);
        $this->assertNull($pageView->referrer_host);
    }

    public function test_country_uses_remote_address_and_ignores_spoofed_headers(): void
    {
        config(['analytics.country_database_path' => base_path('tests/Fixtures/GeoIP2-Country-Test.mmdb')]);

        $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160'])
            ->withHeaders([
                'X-Forwarded-For' => '2001:218::',
                'CF-IPCountry' => 'PH',
                'X-Country-Code' => 'US',
            ])->get(route('blog.index'))->assertOk();

        $pageView = AnalyticsPageView::query()->sole();
        $this->assertSame('GB', $pageView->country_code);
        $this->assertStringNotContainsString('81.2.69.160', implode('|', $pageView->getAttributes()));
        $this->assertStringNotContainsString('2001:218::', implode('|', $pageView->getAttributes()));
    }

    public function test_lookup_failure_still_records_page_view_with_unknown_country(): void
    {
        config(['analytics.country_database_path' => base_path('composer.json')]);

        $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160'])
            ->get(route('blog.index'))->assertOk();

        $this->assertNull(AnalyticsPageView::query()->sole()->country_code);
    }

    public function test_prefetch_and_bot_requests_are_not_recorded(): void
    {
        $this->withHeaders(['Purpose' => 'prefetch'])->get(route('blog.index'))->assertOk();
        $this->withHeaders(['User-Agent' => 'ExampleBot/1.0'])->get(route('blog.index'))->assertOk();

        $this->assertDatabaseCount('analytics_page_views', 0);
    }
}
