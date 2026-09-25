<?php

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['author_id', 'title', 'slug', 'excerpt', 'body_markdown', 'status', 'publish_at', 'published_at', 'seo_title', 'meta_description', 'source', 'created_by_automation'])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (BlogPost $post): void {
            $post->body_html = Str::markdown($post->body_markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'created_by_automation' => 'boolean',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function readingTimeMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->body_html)) / 220));
    }
}
