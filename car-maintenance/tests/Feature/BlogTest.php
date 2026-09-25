<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'How to read your oil dipstick',
            'slug' => 'read-your-oil-dipstick',
            'excerpt' => 'A practical guide to checking engine oil safely and accurately.',
            'body_markdown' => "## Park on level ground\n\nWait five minutes, then check **both sides** of the dipstick.",
            'status' => 'draft',
            'publish_at' => null,
            'seo_title' => 'How to Read an Oil Dipstick',
            'meta_description' => 'Learn how to check engine oil using a dipstick.',
            ...$overrides,
        ];
    }

    public function test_public_blog_lists_only_published_posts_and_hides_drafts_and_future_posts(): void
    {
        $published = BlogPost::factory()->published()->create(['title' => 'Visible article']);
        BlogPost::factory()->create(['title' => 'Draft article']);
        BlogPost::factory()->published()->create([
            'title' => 'Future article',
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('blog.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->has('posts.data', 1)
                ->where('posts.data.0.title', $published->title));

        $this->get(route('blog.show', $published->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('post.title', $published->title)
                ->where('preview', false));
    }

    public function test_unpublished_posts_return_not_found_to_the_public(): void
    {
        $draft = BlogPost::factory()->create();
        $scheduled = BlogPost::factory()->scheduled()->create();

        $this->get(route('blog.show', $draft->slug))->assertNotFound();
        $this->get(route('blog.show', $scheduled->slug))->assertNotFound();
    }

    public function test_blog_admin_requires_authentication_and_admin_permission(): void
    {
        $this->get(route('admin.blog.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.blog.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->blogAdmin()->create())
            ->get(route('admin.blog.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Blog/Admin/Index'));
    }

    public function test_admin_can_create_a_draft_with_sanitized_markdown(): void
    {
        $admin = User::factory()->blogAdmin()->create();
        $payload = $this->payload([
            'slug' => '',
            'body_markdown' => "Hello **driver**.\n\n<script>alert('unsafe')</script>",
        ]);

        $this->actingAs($admin)
            ->post(route('admin.blog.store'), $payload)
            ->assertRedirect();

        $post = BlogPost::firstOrFail();
        $this->assertSame($admin->id, $post->author_id);
        $this->assertSame('how-to-read-your-oil-dipstick', $post->slug);
        $this->assertSame('draft', $post->status);
        $this->assertStringContainsString('<strong>driver</strong>', $post->body_html);
        $this->assertStringNotContainsString('<script>', $post->body_html);
        $this->assertFalse($post->created_by_automation);
    }

    public function test_admin_can_publish_immediately_and_preview_a_draft(): void
    {
        $admin = User::factory()->blogAdmin()->create();
        $draft = BlogPost::factory()->for($admin, 'author')->create();

        $this->actingAs($admin)
            ->get(route('admin.blog.preview', $draft))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('preview', true));

        $this->put(route('admin.blog.update', $draft), $this->payload([
            'slug' => $draft->slug,
            'status' => 'published',
        ]))->assertRedirect();

        $this->assertSame('published', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->published_at);
        $this->get(route('blog.show', $draft->slug))->assertOk();
    }

    public function test_scheduled_posts_require_a_future_publication_time(): void
    {
        $admin = User::factory()->blogAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.blog.store'), $this->payload([
                'status' => 'scheduled',
                'publish_at' => now(config('blog.timezone'))->subMinute()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('publish_at');

        $this->assertDatabaseCount('blog_posts', 0);
    }

    public function test_feed_and_sitemap_include_published_posts_only(): void
    {
        $published = BlogPost::factory()->published()->create();
        $draft = BlogPost::factory()->create();

        $this->get(route('blog.feed'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title);

        $this->get(route('blog.sitemap'))
            ->assertOk()
            ->assertSee(route('blog.show', $published->slug))
            ->assertDontSee(route('blog.show', $draft->slug));
    }

    public function test_blog_policy_allows_only_administrators(): void
    {
        $post = BlogPost::factory()->create();
        $admin = User::factory()->blogAdmin()->create();
        $user = User::factory()->create();

        $this->assertTrue($admin->can('viewAny', BlogPost::class));
        $this->assertTrue($admin->can('create', BlogPost::class));
        $this->assertTrue($admin->can('view', $post));
        $this->assertTrue($admin->can('update', $post));
        $this->assertTrue($admin->can('delete', $post));
        $this->assertFalse($user->can('viewAny', BlogPost::class));
        $this->assertFalse($user->can('create', BlogPost::class));
        $this->assertFalse($user->can('view', $post));
        $this->assertFalse($user->can('update', $post));
        $this->assertFalse($user->can('delete', $post));
    }

    public function test_blog_admin_command_grants_and_revokes_restricted_access(): void
    {
        $user = User::factory()->create();

        $this->artisan('blog:admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_blog_admin);
        $this->assertFalse($user->fresh()->is_trip_admin);

        $this->artisan('blog:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_blog_admin);
    }

    public function test_registration_cannot_self_assign_blog_administration(): void
    {
        $this->post('/register', [
            'name' => 'Editorial applicant',
            'email' => 'editorial@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'country' => 'PH',
            'is_blog_admin' => true,
        ])->assertRedirect('/dashboard');

        $this->assertFalse(User::where('email', 'editorial@example.com')->firstOrFail()->is_blog_admin);
    }
}
