<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogSeoEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_description_falls_back_to_a_truncated_excerpt(): void
    {
        $longExcerpt = str_repeat('Practical maintenance advice for everyday drivers. ', 12); // ~700 chars

        $post = BlogPost::factory()->published()->create([
            'meta_description' => null,
            'excerpt' => $longExcerpt,
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.meta_description', fn (string $value): bool => strlen($value) <= 155 && str_starts_with($value, 'Practical maintenance advice')));

        $this->assertLessThanOrEqual(155, strlen($post->metaDescription()));
    }

    public function test_body_headings_get_anchor_ids_and_a_table_of_contents(): void
    {
        $post = BlogPost::factory()->published()->create([
            'body_markdown' => "## First steps\n\nDo this.\n\n### Tools you need\n\nA wrench.\n\n## Final checks\n\nDone.",
        ]);

        $this->assertStringContainsString('id="first-steps"', $post->body_html);
        $this->assertStringContainsString('id="tools-you-need"', $post->body_html);

        $this->get(route('blog.show', $post->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.toc', [
                    ['level' => 2, 'text' => 'First steps', 'id' => 'first-steps'],
                    ['level' => 3, 'text' => 'Tools you need', 'id' => 'tools-you-need'],
                    ['level' => 2, 'text' => 'Final checks', 'id' => 'final-checks'],
                ]));
    }

    public function test_title_only_edits_do_not_rerender_the_body(): void
    {
        $admin = User::factory()->blogAdmin()->create();
        $post = BlogPost::factory()->create(['title' => 'Original title']);
        $bodyHtml = $post->body_html;

        // Poison derived columns to prove they are not recomputed on non-body saves.
        $post->forceFill(['reading_time_minutes' => 999, 'toc' => null])->saveQuietly();

        $this->actingAs($admin)
            ->put(route('admin.blog.update', $post), [
                'title' => 'Renamed title',
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'body_markdown' => $post->body_markdown,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $post->refresh();
        $this->assertSame('Renamed title', $post->title);
        $this->assertSame($bodyHtml, $post->body_html);
        $this->assertSame(999, $post->reading_time_minutes);
        $this->assertNull($post->toc);
    }

    public function test_paginated_index_pages_have_distinct_titles_and_canonicals(): void
    {
        BlogPost::factory()->published()->count(11)->create();

        $pageOne = $this->get(route('blog.index'));
        $pageOne->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pageTitle', 'Blog')
            ->where('canonicalUrl', route('blog.index')));

        $pageTwo = $this->get(route('blog.index', ['page' => 2]));
        $pageTwo->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pageTitle', 'Blog — Page 2')
            ->where('canonicalUrl', route('blog.index', ['page' => 2])));
    }

    public function test_article_page_emits_json_ld_schema(): void
    {
        $tag = BlogTag::factory()->create(['name' => 'Maintenance']);
        $post = BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create(['title' => 'Schema article']);

        $html = $this->get(route('blog.show', $post->slug))->getContent();

        $this->assertStringContainsString('<script type="application/ld+json" id="page-schema">', $html);

        preg_match('/<script type="application\/ld\+json" id="page-schema">(.*?)<\/script>/s', $html, $matches);
        $schema = json_decode($matches[1], true);

        $this->assertSame('https://schema.org', $schema['@context']);
        $types = collect($schema['@graph'])->pluck('@type')->all();
        $this->assertContains('Article', $types);
        $this->assertContains('BreadcrumbList', $types);

        $article = collect($schema['@graph'])->firstWhere('@type', 'Article');
        $this->assertSame('Schema article', $article['headline']);
        $this->assertSame(route('blog.show', $post->slug), $article['mainEntityOfPage']);
        $this->assertSame(['Maintenance'], $article['articleSection']);
    }

    public function test_feed_includes_full_content_categories_and_self_link(): void
    {
        $tag = BlogTag::factory()->create(['name' => 'PMS']);
        $post = BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create([
            'body_markdown' => "## Feed body\n\nFull text here.",
        ]);

        $xml = $this->get(route('blog.feed'))->getContent();

        $this->assertStringContainsString('xmlns:content=', $xml);
        $this->assertStringContainsString('<atom:link href="'.route('blog.feed').'" rel="self"', $xml);
        $this->assertStringContainsString('<content:encoded>', $xml);
        $this->assertStringContainsString('Feed body', $xml);
        $this->assertStringContainsString('<category>PMS</category>', $xml);
    }

    public function test_sitemap_includes_tag_archives(): void
    {
        $tag = BlogTag::factory()->create(['slug' => 'road-trips']);
        BlogPost::factory()->published()->hasAttached($tag, [], 'tags')->create();
        BlogTag::factory()->create(['slug' => 'draft-only-tag']); // no published posts

        $xml = $this->get(route('blog.sitemap'))->getContent();

        $this->assertStringContainsString(route('blog.tag', 'road-trips'), $xml);
        $this->assertStringNotContainsString(route('blog.tag', 'draft-only-tag'), $xml);
    }

    public function test_public_blog_pages_carry_cache_headers(): void
    {
        $post = BlogPost::factory()->published()->create();

        foreach ([route('blog.index'), route('blog.show', $post->slug)] as $url) {
            $header = $this->get($url)->headers->get('Cache-Control');

            $this->assertStringContainsString('public', $header);
            $this->assertStringContainsString('max-age=300', $header);
            $this->assertStringContainsString('stale-while-revalidate=600', $header);
        }
    }

    public function test_preview_of_published_post_points_canonical_at_the_public_url(): void
    {
        $admin = User::factory()->blogAdmin()->create();
        $post = BlogPost::factory()->published()->for($admin, 'author')->create();

        $this->actingAs($admin)
            ->get(route('admin.blog.preview', $post))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.url', route('blog.show', $post->slug))
                ->where('preview', true));
    }
}
