<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBlogPostRequest;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Response;

class AdminBlogPostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', BlogPost::class);

        $posts = BlogPost::query()
            ->with('author:id,name')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (BlogPost $post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'author' => $post->author->name,
                'publish_at' => $post->publish_at?->toIso8601String(),
                'published_at' => $post->published_at?->toIso8601String(),
                'updated_at' => $post->updated_at->toIso8601String(),
                'edit_url' => route('admin.blog.edit', $post),
                'preview_url' => route('admin.blog.preview', $post),
                'public_url' => $post->status === 'published' ? route('blog.show', $post->slug) : null,
            ]);

        return inertia('Blog/Admin/Index', [
            'posts' => $posts,
            'createUrl' => route('admin.blog.create'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', BlogPost::class);

        return inertia('Blog/Admin/Edit', [
            'post' => null,
            'submitUrl' => route('admin.blog.store'),
            'timezone' => config('blog.timezone'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SaveBlogPostRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated());
        $data['author_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['title']);
        $data['source'] = 'admin';
        $data['created_by_automation'] = false;

        $post = BlogPost::create($data);

        return redirect()->route('admin.blog.edit', $post)->with('success', 'Blog post saved.');
    }

    /**
     * Display the specified resource.
     */
    public function show(BlogPost $blogPost): Response
    {
        $this->authorize('view', $blogPost);
        $blogPost->load('author:id,name');

        return inertia('Blog/Show', [
            'post' => [
                'title' => $blogPost->title,
                'slug' => $blogPost->slug,
                'excerpt' => $blogPost->excerpt,
                'author' => $blogPost->author->name,
                'published_at' => ($blogPost->published_at ?? $blogPost->publish_at ?? $blogPost->updated_at)->toIso8601String(),
                'reading_time' => $blogPost->readingTimeMinutes(),
                'body_html' => $blogPost->body_html,
                'seo_title' => $blogPost->seo_title ?: $blogPost->title,
                'meta_description' => $blogPost->meta_description ?: $blogPost->excerpt,
                'url' => route('admin.blog.preview', $blogPost),
            ],
            'preview' => true,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BlogPost $blogPost): Response
    {
        $this->authorize('update', $blogPost);

        return inertia('Blog/Admin/Edit', [
            'post' => [
                'id' => $blogPost->id,
                'title' => $blogPost->title,
                'slug' => $blogPost->slug,
                'excerpt' => $blogPost->excerpt,
                'body_markdown' => $blogPost->body_markdown,
                'status' => $blogPost->status,
                'publish_at' => $blogPost->publish_at?->clone()->setTimezone(config('blog.timezone'))->format('Y-m-d\TH:i'),
                'seo_title' => $blogPost->seo_title,
                'meta_description' => $blogPost->meta_description,
                'preview_url' => route('admin.blog.preview', $blogPost),
            ],
            'submitUrl' => route('admin.blog.update', $blogPost),
            'timezone' => config('blog.timezone'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SaveBlogPostRequest $request, BlogPost $blogPost): RedirectResponse
    {
        $data = $this->prepareData($request->validated(), $blogPost);
        $data['slug'] = $data['slug'] ?: $blogPost->slug;
        $blogPost->update($data);

        return back()->with('success', 'Blog post updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        $this->authorize('delete', $blogPost);
        $blogPost->update(['status' => 'archived', 'publish_at' => null]);

        return redirect()->route('admin.blog.index')->with('success', 'Blog post archived.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data, ?BlogPost $post = null): array
    {
        if (! empty($data['publish_at'])) {
            $data['publish_at'] = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                $data['publish_at'],
                config('blog.timezone'),
            )->utc();
        }

        if ($data['status'] === 'published') {
            $data['publish_at'] = $data['publish_at'] ?: now();
            $data['published_at'] = $post?->published_at ?: now();
        } elseif ($data['status'] === 'scheduled') {
            $data['published_at'] = null;
        } else {
            $data['publish_at'] = null;
            $data['published_at'] = null;
        }

        return $data;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (BlogPost::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
