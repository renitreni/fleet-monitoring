<?php

namespace App\Console\Commands;

use App\Models\AnalyticsPageView;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:prune-page-views')]
#[Description('Delete raw website analytics page views older than 13 months')]
class PruneAnalyticsPageViews extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = AnalyticsPageView::where('occurred_at', '<', now()->subMonthsNoOverflow(13))->delete();
        $this->info("Deleted {$deleted} expired analytics page views.");

        return self::SUCCESS;
    }
}
