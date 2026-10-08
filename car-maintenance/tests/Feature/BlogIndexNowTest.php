<?php

namespace Tests\Feature;

use App\Jobs\SubmitBlogPostToIndexNow;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BlogIndexNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_a_post_dispatches_an_indexnow_submission(): void
    {
        Bus::fake();
        $post = BlogPost::factory()->create();

        $post->update(['status' => 'published', 'published_at' => now()]);

        Bus::assertDispatched(SubmitBlogPostToIndexNow::class, fn (SubmitBlogPostToIndexNow $job): bool => $job->slug === $post->slug);
    }

    public function test_updating_a_published_post_dispatches_an_indexnow_submission(): void
    {
        Bus::fake();
        $post = BlogPost::factory()->published()->create();

        $post->update(['title' => 'A better title']);

        Bus::assertDispatched(SubmitBlogPostToIndexNow::class);
    }

    public function test_saving_a_draft_does_not_dispatch_an_indexnow_submission(): void
    {
        Bus::fake();
        $post = BlogPost::factory()->create();

        $post->update(['title' => 'Still a draft']);

        Bus::assertNotDispatched(SubmitBlogPostToIndexNow::class);
    }

    public function test_indexnow_job_submits_the_post_url_with_the_verification_key(): void
    {
        config(['app.url' => 'https://motologic.tech']);
        config(['services.indexnow.key' => 'testkey123']);
        URL::forceRootUrl('https://motologic.tech');
        URL::forceScheme('https');

        Http::fake([
            'api.indexnow.org/*' => Http::response([], 200),
        ]);

        $job = new SubmitBlogPostToIndexNow('some-post-slug');
        $this->assertTrue($job->submit());

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.indexnow.org/indexnow'
            && $request['host'] === 'motologic.tech'
            && $request['key'] === 'testkey123'
            && $request['keyLocation'] === 'https://motologic.tech/testkey123.txt'
            && $request['urlList'] === ['https://motologic.tech/blog/some-post-slug']);
    }

    public function test_indexnow_job_returns_false_when_the_endpoint_rejects(): void
    {
        config(['app.url' => 'https://motologic.tech']);

        Http::fake([
            'api.indexnow.org/*' => Http::response(['error' => 'invalid key'], 403),
        ]);

        $job = new SubmitBlogPostToIndexNow('some-post-slug');

        $this->assertFalse($job->submit());
    }
}
