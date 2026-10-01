<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
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
                ->missing('posts.data.0.author')
                ->where('posts.data.0.title', $published->title));

        $this->get(route('blog.show', $published->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('post.title', $published->title)
                ->missing('post.author')
                ->where('preview', false));
    }

    public function test_public_blog_uses_the_domain_as_author_name(): void
    {
        $author = User::factory()->create(['name' => 'Private author name']);
        $post = BlogPost::factory()->published()->for($author, 'author')->create();

        $this->get('https://journal.example:8443/blog')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->where('posts.data.0.author_name', 'journal.example')
                ->missing('posts.data.0.author'));

        $this->get('https://journal.example:8443/blog/'.$post->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('post.author_name', 'journal.example')
                ->missing('post.author'));

        $this->get('https://journal.example:8443/')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('recentPosts.0.author_name', 'journal.example')
                ->missing('recentPosts.0.author'));
    }

    public function test_draft_preview_uses_the_domain_as_author_name(): void
    {
        $admin = User::factory()->blogAdmin()->create(['name' => 'Private admin name']);
        $draft = BlogPost::factory()->for($admin, 'author')->create();

        $this->actingAs($admin)
            ->get('http://preview.example:8000/admin/blog/'.$draft->id.'/preview')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('post.author_name', 'preview.example')
                ->missing('post.author')
                ->where('preview', true));
    }

    public function test_public_blog_pagination_keeps_older_published_posts_accessible(): void
    {
        $this->freezeTime();
        $author = User::factory()->create();
        $posts = BlogPost::factory()->published()->for($author, 'author')->count(19)
            ->sequence(fn (Sequence $sequence): array => [
                'published_at' => now()->subDays(365 + $sequence->index),
            ])
            ->create();
        BlogPost::factory()->for($author, 'author')->create();
        BlogPost::factory()->for($author, 'author')->scheduled()->create();

        $this->get(route('blog.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->has('posts.data', 9)
                ->where('posts.total', 19)
                ->where('posts.current_page', 1)
                ->where('posts.last_page', 3)
                ->where('posts.prev_page_url', null)
                ->where('posts.next_page_url', route('blog.index', ['page' => 2]))
                ->where('posts.data.0.slug', $posts[0]->slug)
                ->where('posts.data.8.slug', $posts[8]->slug));

        $this->get(route('blog.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->has('posts.data', 9)
                ->where('posts.total', 19)
                ->where('posts.current_page', 2)
                ->where('posts.prev_page_url', route('blog.index', ['page' => 1]))
                ->where('posts.next_page_url', route('blog.index', ['page' => 3]))
                ->where('posts.data.0.slug', $posts[9]->slug)
                ->where('posts.data.8.slug', $posts[17]->slug));

        $this->get(route('blog.index', ['page' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->has('posts.data', 1)
                ->where('posts.total', 19)
                ->where('posts.current_page', 3)
                ->where('posts.prev_page_url', route('blog.index', ['page' => 2]))
                ->where('posts.next_page_url', null)
                ->where('posts.data.0.slug', $posts[18]->slug)
                ->missing('posts.data.0.author'));

        $this->get(route('blog.show', $posts[18]->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('post.slug', $posts[18]->slug));
    }

    public function test_public_blog_keeps_pagination_metadata_for_an_out_of_range_page(): void
    {
        BlogPost::factory()->published()->create();

        $this->get(route('blog.index', ['page' => 99]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->has('posts.data', 0)
                ->where('posts.total', 1)
                ->where('posts.first_page_url', route('blog.index', ['page' => 1])));
    }

    public function test_landing_page_lists_the_three_latest_published_posts_only(): void
    {
        $this->freezeTime();
        $latest = BlogPost::factory()->published()->create(['title' => 'Latest article', 'published_at' => now()->subHour()]);
        $second = BlogPost::factory()->published()->create(['title' => 'Second article', 'published_at' => now()->subHours(2)]);
        $third = BlogPost::factory()->published()->create(['title' => 'Third article', 'published_at' => now()->subHours(3)]);
        BlogPost::factory()->published()->create(['title' => 'Older article', 'published_at' => now()->subHours(4)]);
        BlogPost::factory()->create(['title' => 'Draft article']);
        BlogPost::factory()->published()->create(['title' => 'Future article', 'published_at' => now()->addHour()]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('recentPosts', 3)
                ->where('recentPosts.0.title', $latest->title)
                ->where('recentPosts.0.url', route('blog.show', $latest->slug))
                ->where('recentPosts.1.title', $second->title)
                ->where('recentPosts.2.title', $third->title)
                ->missing('recentPosts.0.author')
                ->missing('recentPosts.0.body_html')
                ->missing('recentPosts.0.body_markdown'));
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

    public function test_blog_admin_can_reach_posts_beyond_the_first_page(): void
    {
        $this->freezeTime();
        $admin = User::factory()->blogAdmin()->create();
        $posts = BlogPost::factory()->for($admin, 'author')->count(21)
            ->sequence(fn (Sequence $sequence): array => [
                'updated_at' => now()->subDays($sequence->index),
            ])
            ->create();

        $this->actingAs($admin)->get(route('admin.blog.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Admin/Index')
                ->has('posts.data', 20)
                ->where('posts.total', 21)
                ->where('posts.next_page_url', route('admin.blog.index', ['page' => 2]))
                ->missing('posts.data.0.author'));

        $this->get(route('admin.blog.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Admin/Index')
                ->has('posts.data', 1)
                ->where('posts.current_page', 2)
                ->where('posts.data.0.id', $posts[20]->id)
                ->where('posts.prev_page_url', route('admin.blog.index', ['page' => 1]))
                ->where('posts.next_page_url', null));
    }

    public function test_admin_can_publish_immediately_and_preview_a_draft(): void
    {
        $admin = User::factory()->blogAdmin()->create();
        $draft = BlogPost::factory()->for($admin, 'author')->create();

        $this->actingAs($admin)
            ->get(route('admin.blog.preview', $draft))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->missing('post.author')
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
