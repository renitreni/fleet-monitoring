<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_archive_lists_only_published_posts_with_that_tag(): void
    {
        $tag = BlogTag::factory()->create(['name' => 'Maintenance', 'slug' => 'maintenance']);
        $matching = BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create(['title' => 'Matching article']);
        BlogPost::factory()->published()->create(['title' => 'Other article']);
        BlogPost::factory()->hasAttached($tag, [], 'tags')->create(['title' => 'Draft article']);

        $this->get(route('blog.tag', 'maintenance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->where('heading.title', 'Maintenance')
                ->where('canonicalUrl', route('blog.tag', 'maintenance'))
                ->has('posts.data', 1)
                ->where('posts.data.0.title', $matching->title));
    }

    public function test_tag_archive_is_not_excluded_from_ssr(): void
    {
        // HandleInertiaRequests disables SSR outside an allowlist of blog
        // routes; the tag archive must stay in it or crawlers get an empty
        // client-rendered shell.
        $tag = BlogTag::factory()->create(['name' => 'Maintenance', 'slug' => 'maintenance']);
        BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create();

        $this->get(route('blog.tag', 'maintenance'))->assertOk();

        $gateway = app(\Inertia\Ssr\HttpGateway::class);
        $disabled = new \ReflectionProperty($gateway, 'disabled');
        $this->assertFalse($disabled->getValue($gateway)());
    }

    public function test_tag_archive_404s_for_unknown_slug(): void
    {
        $this->get('/blog/tag/does-not-exist')->assertNotFound();
    }

    public function test_tag_route_takes_precedence_over_slug_route(): void
    {
        BlogPost::factory()->published()->create(['slug' => 'my-story']);

        $this->get('/blog/tag/maintenance')->assertNotFound();
        $this->get('/blog/my-story')->assertOk();
    }

    public function test_admin_can_sync_tags_when_creating_and_updating_a_post(): void
    {
        $admin = \App\Models\User::factory()->blogAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.blog.store'), [
                'title' => 'Tagged post',
                'slug' => 'tagged-post',
                'excerpt' => 'An excerpt for the tagged post.',
                'body_markdown' => "## Intro\n\nBody text.",
                'status' => 'draft',
                'tags' => ['Maintenance', 'Toyota'],
            ])
            ->assertRedirect();

        $post = BlogPost::query()->where('slug', 'tagged-post')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Maintenance', 'Toyota'], $post->tags->pluck('name')->all());

        $this->actingAs($admin)
            ->put(route('admin.blog.update', $post), [
                'title' => 'Tagged post',
                'slug' => 'tagged-post',
                'excerpt' => 'An excerpt for the tagged post.',
                'body_markdown' => "## Intro\n\nBody text.",
                'status' => 'draft',
                'tags' => ['Oil change'],
            ])
            ->assertRedirect();

        $this->assertEquals(['Oil change'], $post->tags()->pluck('name')->all());
    }

    public function test_post_payload_includes_tag_links(): void
    {
        $tag = BlogTag::factory()->create(['name' => 'Road trips', 'slug' => 'road-trips']);
        $post = BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create();

        $this->get(route('blog.show', $post->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.tags.0.name', 'Road trips')
                ->where('post.tags.0.url', route('blog.tag', 'road-trips')));
    }
}
