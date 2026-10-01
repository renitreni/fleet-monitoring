<?php

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Support\Facades\DB;

class BlogPostViewRecorder
{
    public function record(int $postId, string $sessionHash, string $viewedOn): void
    {
        DB::transaction(function () use ($postId, $sessionHash, $viewedOn): void {
            $inserted = DB::table('blog_post_views')->insertOrIgnore([
                'blog_post_id' => $postId,
                'session_hash' => $sessionHash,
                'viewed_on' => $viewedOn,
            ]);

            if ($inserted === 1) {
                BlogPost::withTrashed()->whereKey($postId)->toBase()->increment('views_count');
            }
        }, 3);
    }
}
