<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Response;

class BlogController extends Controller
{
    /** Columns needed to render post summaries (keeps longtext bodies off listing queries). */
    private const SUMMARY_COLUMNS = [
        'id',
        'author_id',
        'byline',
        'title',
        'slug',
        'excerpt',
        'status',
        'publish_at',
        'published_at',
        'updated_at',
        'cover_image',
        'reading_time_minutes',
        'views_count',
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $query = BlogPost::query()
            ->published()
            ->select(self::SUMMARY_COLUMNS)
            ->with(['tags:id,name,slug']);

        if ($search !== '') {
            $query->search($search);
        }

        $posts = $query->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9)
            ->onEachSide(1)
            ->through(fn (BlogPost $post): array => $this->summary($post))
            ->withQueryString();

        $page = $posts->currentPage();

        return inertia('Blog/Index', [
            'posts' => $posts,
            'canonicalUrl' => $search !== ''
                ? route('blog.index', ['q' => $search])
                : ($page > 1 ? route('blog.index', ['page' => $page]) : route('blog.index')),
            'pageTitle' => $search !== '' ? "Search: {$search}" : ($page > 1 ? "Blog — Page {$page}" : 'Blog'),
            'noindex' => $search !== '' && $posts->total() === 0,
            'searchQuery' => $search,
            'heading' => null,
        ]);
    }

    public function tag(Request $request, BlogTag $blogTag): Response
    {
        $posts = $blogTag->posts()
            ->published()
            ->select(self::SUMMARY_COLUMNS)
            ->with(['tags:id,name,slug'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9)
            ->onEachSide(1)
            ->through(fn (BlogPost $post): array => $this->summary($post))
            ->withQueryString();

        $page = $posts->currentPage();

        return inertia('Blog/Index', [
            'posts' => $posts,
            'canonicalUrl' => $page > 1
                ? route('blog.tag', ['blogTag' => $blogTag->slug, 'page' => $page])
                : route('blog.tag', $blogTag->slug),
            'pageTitle' => $page > 1 ? "{$blogTag->name} — Page {$page}" : $blogTag->name,
            'noindex' => $posts->total() === 0,
            'searchQuery' => '',
            'heading' => [
                'eyebrow' => 'Motologic journal',
                'title' => $blogTag->name,
                'description' => "Every Motologic journal article tagged “{$blogTag->name}”.",
            ],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $post = BlogPost::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $request->attributes->set('blog_post_id', $post->id);

        return inertia('Blog/Show', [
            'post' => $this->article($post),
            'related' => BlogPost::query()
                // Select first: the related() scope appends its tags_count
                // subquery via addSelect, and a later select() would drop it,
                // leaving an ORDER BY on an alias MySQL rejects.
                ->select(self::SUMMARY_COLUMNS)
                ->related($post)
                ->with(['tags:id,name,slug'])
                ->limit(3)
                ->get()
                ->map(fn (BlogPost $related): array => $this->summary($related))
                ->all(),
            'schema' => $this->articleSchema($post),
            'preview' => false,
        ]);
    }

    public function feed(): HttpResponse
    {
        $posts = BlogPost::query()
            ->published()
            ->with('tags:id,name')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()->view('blog.feed', ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function sitemap(): HttpResponse
    {
        $posts = BlogPost::query()
            ->published()
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        $tags = BlogTag::query()
            ->withMax(['posts' => fn ($query): mixed => $query->published()], 'updated_at')
            ->get();

        return response()->view('blog.sitemap', ['posts' => $posts, 'tags' => $tags])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** @return array<string, mixed> */
    private function summary(BlogPost $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'author_name' => $post->bylineName(),
            'published_at' => $post->published_at->toIso8601String(),
            'reading_time' => $post->readingTimeMinutes(),
            'url' => route('blog.show', $post->slug),
            'cover_image_url' => $post->coverImageUrl(),
            'tags' => $post->tags->map(fn (BlogTag $tag): array => [
                'name' => $tag->name,
                'slug' => $tag->slug,
                'url' => route('blog.tag', $tag->slug),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function article(BlogPost $post): array
    {
        return [
            ...$this->summary($post),
            'views_count' => $post->views_count,
            'body_html' => $post->body_html,
            'toc' => $post->toc ?? [],
            'seo_title' => $post->seo_title ?: $post->title,
            'meta_description' => $post->metaDescription(),
        ];
    }

    /** @return array<string, mixed> */
    private function articleSchema(BlogPost $post): array
    {
        $article = [
            '@type' => 'Article',
            '@id' => route('blog.show', $post->slug).'#article',
            'headline' => $post->seo_title ?: $post->title,
            'description' => $post->metaDescription(),
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at->toAtomString(),
            'author' => [
                '@type' => 'Organization',
                'name' => $post->bylineName(),
                'url' => route('home'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => route('home'),
            ],
            'mainEntityOfPage' => route('blog.show', $post->slug),
            'timeRequired' => 'PT'.$post->readingTimeMinutes().'M',
            'wordCount' => str_word_count(strip_tags($post->body_html)),
            'articleSection' => $post->tags->pluck('name')->values()->all(),
        ];

        if ($cover = $post->coverImageUrl()) {
            $article['image'] = $cover;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $article,
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Home',
                            'item' => route('home'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Blog',
                            'item' => route('blog.index'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $post->title,
                            'item' => route('blog.show', $post->slug),
                        ],
                    ],
                ],
            ],
        ];
    }
}
