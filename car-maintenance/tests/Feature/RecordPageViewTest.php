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

    public function test_prefetch_and_bot_requests_are_not_recorded(): void
    {
        $this->withHeaders(['Purpose' => 'prefetch'])->get(route('blog.index'))->assertOk();
        $this->withHeaders(['User-Agent' => 'ExampleBot/1.0'])->get(route('blog.index'))->assertOk();

        $this->assertDatabaseCount('analytics_page_views', 0);
    }
}
