<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filters_posts_by_title_and_excerpt(): void
    {
        BlogPost::factory()->published()->create([
            'title' => 'How to change your oil filter',
            'excerpt' => 'Step by step guide.',
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'Best road trips in Luzon',
            'excerpt' => 'Scenic drives for the weekend.',
        ]);

        $this->get('/blog?q=oil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'How to change your oil filter')
                ->where('searchQuery', 'oil')
                ->where('pageTitle', 'Search: oil'));
    }

    public function test_empty_search_results_are_noindexed(): void
    {
        BlogPost::factory()->published()->create(['title' => 'Oil change basics']);

        $this->get('/blog?q=zzzznothingmatches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('noindex', true)
                ->where('canonicalUrl', route('blog.index', ['q' => 'zzzznothingmatches'])));
    }

    public function test_search_excludes_unpublished_posts(): void
    {
        BlogPost::factory()->create([
            'title' => 'Secret oil formula',
            'excerpt' => 'Draft only.',
        ]);

        $this->get('/blog?q=oil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 0));
    }
}
