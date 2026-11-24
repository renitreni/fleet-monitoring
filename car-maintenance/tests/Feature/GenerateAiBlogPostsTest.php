<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateAiBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_ten_scheduled_posts_and_enforces_the_daily_quota(): void
    {
        $this->travelTo('2026-11-24 00:00:00');
        Http::preventStrayRequests();
        config(['blog.ai.api_key' => 'test-nvidia-key']);
        $author = User::factory()->create(['is_blog_admin' => true]);
        $requestNumber = 0;
        Http::fake([
            'integrate.api.nvidia.com/v1/chat/completions' => function () use (&$requestNumber) {
                $requestNumber++;

                return Http::response([
                    'choices' => [
                        ['message' => ['content' => json_encode([
                            'title' => "Automotive Maintenance Guide {$requestNumber}",
                            'excerpt' => 'Practical maintenance guidance to help owners make informed decisions about their vehicles.',
                            'body_markdown' => '## Practical guidance'.PHP_EOL.str_repeat('Reliable maintenance information for vehicle owners. ', 20),
                            'seo_title' => "Maintenance Guide {$requestNumber}",
                            'meta_description' => 'Learn practical maintenance checks and habits that support safer, more reliable vehicle ownership throughout the year.',
                            'tags' => ['Maintenance', 'Safety'],
                        ])]],
                    ],
                ]);
            },
        ]);

        $this->artisan('blog:generate-ai')
            ->expectsOutput('Created 10 AI blog post(s).')
            ->assertSuccessful();

        $posts = BlogPost::query()->orderBy('publish_at')->get();
        $this->assertSame(10, $posts->count());
        $this->assertSame([
            '2026-11-24 08:00:00',
            '2026-11-24 09:00:00',
            '2026-11-24 10:00:00',
            '2026-11-24 11:00:00',
            '2026-11-24 12:00:00',
            '2026-11-24 13:00:00',
            '2026-11-24 14:00:00',
            '2026-11-24 15:00:00',
            '2026-11-24 16:00:00',
            '2026-11-24 17:00:00',
        ], $posts->map(fn (BlogPost $post): string => $post->publish_at->setTimezone('Asia/Manila')->toDateTimeString())->all());
        $this->assertSame([$author->id], $posts->pluck('author_id')->unique()->values()->all());
        $this->assertSame(['scheduled'], $posts->pluck('status')->unique()->values()->all());
        $this->assertSame(['nvidia'], $posts->pluck('source')->unique()->values()->all());
        $this->assertSame(['Motologic Editorial Team'], $posts->pluck('byline')->unique()->values()->all());
        $this->assertSame(['Maintenance', 'Safety'], $posts->first()->tags->pluck('name')->sort()->values()->all());
        $this->assertDatabaseHas('blog_posts', [
            'title' => 'Automotive Maintenance Guide 1',
            'slug' => 'automotive-maintenance-guide-1',
            'created_by_automation' => true,
        ]);
        Http::assertSentCount(10);

        $this->artisan('blog:generate-ai')
            ->expectsOutput('Daily AI blog quota already reached (10).')
            ->assertSuccessful();
        $this->assertSame(10, BlogPost::count());
        Http::assertSentCount(10);
    }

    public function test_command_can_fill_a_partial_daily_quota_after_an_api_failure(): void
    {
        $this->travelTo('2026-11-24 00:00:00');
        Http::preventStrayRequests();
        config(['blog.ai.api_key' => 'test-nvidia-key']);
        User::factory()->create(['is_blog_admin' => true]);
        Http::fake([
            'integrate.api.nvidia.com/v1/chat/completions' => Http::sequence()
                ->push(['error' => 'Unavailable'], 503)
                ->push($this->apiArticle()),
        ]);

        $this->artisan('blog:generate-ai --count=1')
            ->expectsOutput('NVIDIA API request failed with status 503.')
            ->assertFailed();
        $this->assertSame(0, BlogPost::count());

        $this->artisan('blog:generate-ai --count=1')
            ->expectsOutput('Created 1 AI blog post(s).')
            ->assertSuccessful();
        $this->assertDatabaseHas('blog_posts', [
            'title' => 'A Practical Maintenance Guide',
            'status' => 'scheduled',
            'source' => 'nvidia',
        ]);
        Http::assertSentCount(2);
    }

    public function test_command_requires_a_blog_administrator_before_calling_the_api(): void
    {
        Http::preventStrayRequests();
        config(['blog.ai.api_key' => 'test-nvidia-key']);

        $this->artisan('blog:generate-ai --count=1')
            ->expectsOutput('No blog administrator exists. Grant access with blog:admin first.')
            ->assertFailed();

        $this->assertSame(0, BlogPost::count());
        Http::assertNothingSent();
    }

    public function test_count_option_must_be_a_positive_integer(): void
    {
        $this->artisan('blog:generate-ai --count=0')
            ->expectsOutput('The --count option must be a positive integer.')
            ->assertExitCode(2);

        $this->assertSame(0, BlogPost::count());
    }

    /** @return array<string, mixed> */
    private function apiArticle(): array
    {
        return [
            'choices' => [
                ['message' => ['content' => json_encode([
                    'title' => 'A Practical Maintenance Guide',
                    'excerpt' => 'Practical maintenance guidance to help owners make informed decisions about their vehicles.',
                    'body_markdown' => '## Practical guidance'.PHP_EOL.str_repeat('Reliable maintenance information for vehicle owners. ', 20),
                    'seo_title' => 'Practical Vehicle Maintenance Guide',
                    'meta_description' => 'Learn practical maintenance checks and habits that support safer, more reliable vehicle ownership throughout the year.',
                    'tags' => ['Maintenance', 'Safety'],
                ])]],
            ],
        ];
    }
}
