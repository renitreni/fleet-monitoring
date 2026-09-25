<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('blog:publish-scheduled')]
#[Description('Publish blog posts whose scheduled publication time has arrived')]
class PublishScheduledBlogPosts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $published = 0;

        BlogPost::query()
            ->where('status', 'scheduled')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$published): void {
                DB::transaction(function () use ($id, &$published): void {
                    $post = BlogPost::query()->lockForUpdate()->find($id);

                    if (! $post || $post->status !== 'scheduled' || $post->publish_at?->isFuture()) {
                        return;
                    }

                    $post->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ]);
                    $published++;
                });
            });

        $this->info("Published {$published} scheduled blog post(s).");

        return self::SUCCESS;
    }
}
