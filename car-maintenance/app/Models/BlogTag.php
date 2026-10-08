<?php

namespace App\Models;

use Database\Factories\BlogTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class BlogTag extends Model
{
    /** @use HasFactory<BlogTagFactory> */
    use HasFactory;

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }

    public function scopePublishedPosts(Builder $query): Builder
    {
        return $query->whereHas('posts', fn (Builder $q): Builder => $q->where('status', 'published'));
    }

    protected static function booted(): void
    {
        static::saving(function (BlogTag $tag): void {
            $tag->slug = $tag->slug ?: Str::slug($tag->name);
        });
    }
}
