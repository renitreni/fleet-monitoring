<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishScheduledBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_publishes_due_posts_and_leaves_future_and_draft_posts_unchanged(): void
    {
        $this->travelTo('2026-09-25 09:00:00');
        $due = BlogPost::factory()->scheduled()->create(['publish_at' => now()->subMinute()]);
        $future = BlogPost::factory()->scheduled()->create(['publish_at' => now()->addMinute()]);
        $draft = BlogPost::factory()->create(['publish_at' => now()->subHour()]);

        $this->artisan('blog:publish-scheduled')
            ->expectsOutput('Published 1 scheduled blog post(s).')
            ->assertSuccessful();

        $this->assertSame('published', $due->fresh()->status);
        $this->assertSame(now()->timestamp, $due->fresh()->published_at->timestamp);
        $this->assertSame('scheduled', $future->fresh()->status);
        $this->assertNull($future->fresh()->published_at);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->published_at);

        $this->artisan('blog:publish-scheduled')
            ->expectsOutput('Published 0 scheduled blog post(s).')
            ->assertSuccessful();
    }
}
