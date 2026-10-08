<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogRelatedPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_includes_up_to_three_related_published_posts(): void
    {
        $tag = BlogTag::factory()->create();
        $post = BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create();
        $related = BlogPost::factory()->published()->count(4)->hasAttached($tag, [], 'tags')->create();

        BlogPost::factory()->hasAttached($tag, [], 'tags')->create(['title' => 'Draft related']);
        BlogPost::factory()->published()->create(['title' => 'Untagged article']);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('related', 3)
                ->missing('related.3'));
    }

    public function test_related_posts_rank_shared_tags_first(): void
    {
        $tag = BlogTag::factory()->create();
        $post = BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create();
        $sharedTag = BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create(['title' => 'Shared tag article']);
        BlogPost::factory()->published()->create(['title' => 'Untagged article']);

        $this->get(route('blog.show', $post->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('related.0.title', 'Shared tag article'));
    }
}
