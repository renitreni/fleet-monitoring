<?php

namespace Tests\Feature;

use App\Models\AnalyticsPageView;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PruneAnalyticsPageViewsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_deletes_only_page_views_older_than_thirteen_months(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        $expired = AnalyticsPageView::factory()->create(['occurred_at' => '2025-08-24 11:59:59']);
        $retained = AnalyticsPageView::factory()->create(['occurred_at' => '2025-08-25 12:00:00']);

        $this->artisan('analytics:prune-page-views')
            ->expectsOutput('Deleted 1 expired analytics page views.')
            ->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($retained);
    }
}
