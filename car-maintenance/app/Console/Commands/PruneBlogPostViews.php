<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('blog:prune-views')]
#[Description('Delete blog view deduplication records older than seven days')]
class PruneBlogPostViews extends Command
{
    public function handle(): int
    {
        $deleted = DB::table('blog_post_views')
            ->where('viewed_on', '<', now(config('blog.timezone'))->subDays(7)->toDateString())
            ->delete();

        $this->info("Deleted {$deleted} expired blog view records.");

        return self::SUCCESS;
    }
}
