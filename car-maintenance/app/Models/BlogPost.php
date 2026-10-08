<?php

namespace App\Models;

use App\Jobs\SubmitBlogPostToIndexNow;
use App\Support\BlogPostHtmlProcessor;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['author_id', 'byline', 'title', 'slug', 'excerpt', 'body_markdown', 'status', 'publish_at', 'published_at', 'seo_title', 'meta_description', 'cover_image', 'reading_time_minutes', 'source', 'created_by_automation'])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (BlogPost $post): void {
            if ($post->isDirty('body_markdown')) {
                $processed = app(BlogPostHtmlProcessor::class)->process(Str::markdown($post->body_markdown, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]));

                $post->body_html = $processed['html'];
                $post->reading_time_minutes = self::computeReadingTime($processed['html']);
                $post->toc = $processed['toc'];
            }
        });

        static::saved(function (BlogPost $post): void {
            // Notify IndexNow when a post is published or an already-public
            // post materially changes (publish, update, slug change).
            if ($post->status !== 'published' || $post->published_at === null) {
                return;
            }

            if ($post->wasChanged()) {
                SubmitBlogPostToIndexNow::dispatch($post->slug);
            }
        });

        static::restoring(function (BlogPost $post): void {
            if (! static::withTrashed()->where('slug', $post->slug)->whereKeyNot($post->id)->exists()) {
                return;
            }

            $base = $post->slug;
            $slug = $base.'-2';
            $suffix = 2;

            while (static::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.(++$suffix);
            }

            $post->slug = $slug;
        });
    }

    protected function casts(): array
    {
        return [
            'created_by_automation' => 'boolean',
            'views_count' => 'integer',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'reading_time_minutes' => 'integer',
            'toc' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeSearch(Builder $query, string $term): void
    {
        if (DB::getDriverName() === 'mysql') {
            $query->whereFullText(['title', 'excerpt'], $term);

            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $q): Builder => $q->where('title', 'like', $like)
            ->orWhere('excerpt', 'like', $like));
    }

    public function scopeRelated(Builder $query, BlogPost $post): void
    {
        $tagIds = $post->tags()->pluck('blog_tags.id');

        $query->published()
            ->whereKeyNot($post->id)
            ->withCount(['tags' => fn (Builder $q): Builder => $q->whereIn('blog_tags.id', $tagIds)])
            ->orderByDesc('tags_count')
            ->orderByDesc('published_at');
    }

    public function readingTimeMinutes(): int
    {
        return max(1, (int) $this->reading_time_minutes);
    }

    public static function computeReadingTime(string $bodyHtml): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($bodyHtml)) / 220));
    }

    public function metaDescription(): string
    {
        return Str::limit((string) ($this->meta_description ?: $this->excerpt), 155, '');
    }

    public function bylineName(): string
    {
        return $this->byline
            ?: (string) parse_url(route('home'), PHP_URL_HOST);
    }

    public function coverImageUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        if (Str::startsWith($this->cover_image, ['http://', 'https://', '//'])) {
            return $this->cover_image;
        }

        return Storage::disk(config('blog.image_disk'))->url($this->cover_image);
    }
}
