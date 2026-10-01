<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9)
            ->onEachSide(1)
            ->through(fn (BlogPost $post): array => $this->summary($post));

        return inertia('Blog/Index', ['posts' => $posts]);
    }

    public function show(string $slug): Response
    {
        $post = BlogPost::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return inertia('Blog/Show', [
            'post' => $this->article($post),
            'preview' => false,
        ]);
    }

    public function feed(): HttpResponse
    {
        $posts = BlogPost::query()
            ->published()
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

        return response()->view('blog.sitemap', ['posts' => $posts])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** @return array<string, mixed> */
    private function summary(BlogPost $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'author_name' => parse_url(route('home'), PHP_URL_HOST),
            'published_at' => $post->published_at->toIso8601String(),
            'reading_time' => $post->readingTimeMinutes(),
            'url' => route('blog.show', $post->slug),
        ];
    }

    /** @return array<string, mixed> */
    private function article(BlogPost $post): array
    {
        return [
            ...$this->summary($post),
            'body_html' => $post->body_html,
            'seo_title' => $post->seo_title ?: $post->title,
            'meta_description' => $post->meta_description ?: $post->excerpt,
        ];
    }
}
