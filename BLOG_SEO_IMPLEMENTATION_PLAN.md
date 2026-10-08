# Blog Section — SEO & Architecture Implementation Plan

Prepared: 2026-10-08
Scope: all 14 findings from the blog audit (covering `car-maintenance/` Laravel + Inertia app).
Each phase is independently shippable and ends green (tests + build).

Item references (#n) map to the audit findings.

---

## Open decisions (resolve before Phase 1)

| Decision | Recommendation |
|---|---|
| Cover image storage | Store on the `public` disk (`storage/app/public/blog-covers`), served via `php artisan storage:link`. Optional S3 later — keep the code disk-agnostic (`Storage::disk(config('blog.image_disk', 'public'))`). |
| Tags input UX | Comma-separated text input in admin (simple, no JS deps), normalized server-side. |
| JSON-LD delivery | Echo a `schema` page prop in `resources/views/app.blade.php` inside `<head>` (SSR-safe, crawler-visible). Do **not** use Inertia `<Head>` — it only renders title/meta/link. |
| Blog search | Server-rendered `?q=` results on `Blog/Index` using `FULLTEXT` on MySQL with a `LIKE` fallback for SQLite (tests). No dedicated results page. |
| Reading time | Denormalize to a `reading_time_minutes` column, computed only when `body_markdown` is dirty. |

---

## Phase 0 — Database migrations

New migration files (order matters):

1. `2026_10_08_000001_add_seo_columns_to_blog_posts_table.php`
   - `cover_image` → nullable `string(2048)` after `meta_description` (stores disk path or absolute URL)
   - `byline` → nullable `string(120)` after `author_id`
   - `reading_time_minutes` → `unsignedSmallInteger` default 1
   - Backfill: one-off command or `DB::statement` in migration to populate `reading_time_minutes`
     from existing `body_html` (or accept default 1 and let the next save fix it — recommend backfill in migration for correctness).
2. `2026_10_08_000002_create_blog_tags_tables.php`
   - `blog_tags`: `id`, `name` (string 80), `slug` (string 100, unique), timestamps
   - `blog_post_tag`: `blog_post_id` FK → `blog_posts` cascadeOnDelete, `blog_tag_id` FK → `blog_tags` cascadeOnDelete, composite PK, index on `blog_tag_id`
3. `2026_10_08_000003_add_fulltext_to_blog_posts.php`
   - `$table->fullText(['title', 'excerpt'])` — **MySQL only**. Wrap in `if (DB::getDriverName() === 'mysql')` (or use a closure-guarded `Blueprint`) so SQLite test runs don't fail; tests exercise the `LIKE` fallback path.

Seed/dev note: add 2–3 example tags to `BlogPostFactory` (or a `TagFactory`) for tests.

---

## Phase 1 — Model, requests, services (backend core)

### `app/Models/BlogPost.php`
- Fillable += `cover_image`, `byline`, `reading_time_minutes`; add `tags(): BelongsToMany`.
- `booted()` saving hook — gate the expensive work (items #11, #12):

```php
static::saving(function (BlogPost $post): void {
    if ($post->isDirty('body_markdown')) {
        $post->body_html = Str::markdown($post->body_markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $post->reading_time_minutes = max(1, (int) ceil(
            str_word_count(strip_tags($post->body_html)) / 220
        ));
    }
});
```

- New accessors/helpers:
  - `coverImageUrl(): ?string` — `Storage::disk(...)->url($this->cover_image)` for stored paths, passthrough for `http(s)://` values.
  - `metaDescription(): string` — `Str::limit($this->meta_description ?: $this->excerpt, 155, '')` (item #5).
  - `bylineName(): string` — `$this->byline ?: $this->author?->name ?: parse_url(route('home'), PHP_URL_HOST)` (item #3).
  - `readingTimeMinutes()` now returns the column; keep method name so callers are unchanged.
  - `scopeSearch(Builder $query, string $term)` — FULLTEXT boolean mode on MySQL (`whereFullText`), `orWhere(title/excerpt like %term%)` otherwise.
  - `scopeRelated(Builder $query, BlogPost $post)` — published, exclude self, order by shared-tag count:

```php
$query->published()
    ->whereKeyNot($post->id)
    ->leftJoin('blog_post_tag as t_self', ...)
    ->select('blog_posts.*')->selectRaw('COUNT(shared.blog_tag_id) AS shared_tags')
    ->groupBy('blog_posts.id')->orderByDesc('shared_tags')->orderByDesc('published_at');
```

### `app/Http/Requests/SaveBlogPostRequest.php`
- Add rules: `cover_image` (`nullable`, either `url` max 2048 **or** `image` max 4096 KB, mimes jpg/png/webp), `byline` (`nullable string max 120`), `tags` (`nullable array max 10`, each `string max 80`).
- `prepareForValidation()`: trim tags, drop empties.

### `app/Services/BlogPostViewRecorder.php`
- No change.

### Image upload handling
- In `AdminBlogPostController::store/update`: if `$request->hasFile('cover_image')`, store to `blog-covers` and set `cover_image` to the returned path. Form must send `multipart` (Inertia `useForm` with `forceFormData: true` when a file is present, or always for this form).
- Add a `DELETE` route/endpoint or a `cover_image_remove` boolean to clear an existing cover.

---

## Phase 2 — Public controllers & SEO payload

### `app/Http/Controllers/BlogController.php`

- **Item #10 (selects):** add a `$summaryColumns` array (`id, title, slug, excerpt, status, publish_at, published_at, updated_at, cover_image, byline, reading_time_minutes, views_count, author_id`) and `->select(...)` on index/home/related queries. `show()` keeps full row.
- **Item #4 (pagination SEO):** in `index()`, pass `page` and `hasPages`; build title as `Blog — Page N` when `page > 1`; pass `canonical_url` = current URL **without** `page` param when page 1, with param otherwise.
- **`show()` additions:**
  - Pass `cover_image_url`, `meta_description` via `metaDescription()`, `byline`, `tags` (name/slug pairs).
  - Pass `related` (3 posts via `scopeRelated`, mapped through `summary()`).
  - Pass `toc` — heading list extracted from `body_html` (see processor below).
  - Pass `schema` (array) for `app.blade.php` to echo as JSON-LD (item #2):

```php
'@context' => 'https://schema.org', '@type' => 'Article',
'headline' => $post->seo_title ?: $post->title,
'description' => $post->metaDescription(),
'datePublished' => $post->published_at?->toAtomString(),
'dateModified' => $post->updated_at->toAtomString(),
'author' => ['@type' => 'Organization', 'name' => $post->bylineName(), 'url' => route('home')],
'image' => $post->coverImageUrl(),
'mainEntityOfPage' => route('blog.show', $post->slug),
'timeRequired' => 'PT' . $post->reading_time_minutes . 'M',
```
  - Also a `BreadcrumbList` node (Home → Blog → Article).

### New: `app/Support/BlogPostHtmlProcessor.php` (or `Services/`)
- `process(string $bodyHtml): array{html: string, toc: array}` — DOMDocument walk: inject `id` anchors (`Str::slug` of heading text, deduped) into `h2`/`h3`, return ordered `[{level, text, id}]` for the table of contents (item #9).
- Run once at save time (store processed `body_html` + `toc` JSON column) — cheaper than per-request. Add `toc` JSON column in Phase 0 migration 1.

### Tag archive (items #6, #7)
- Route: `Route::get('/blog/tag/{tag}', [BlogController::class, 'tag'])->name('blog.tag');` **before** `/blog/{slug}`.
- `tag(BlogTag $tag)` — published posts for tag, paginated 9, `Blog/Index` reused with `heading` props (title `"{Tag} articles"`, canonical, meta description, noindex if empty).
- `BlogTag` model + factory; route-model binding on `slug`.

### Search (item #9)
- `index()` accepts `?q=`; when present, apply `scopeSearch`, set `noindex` hint if results empty (thin page), title `Search: {q}`.

### Home page (`routes/web.php` closure, line 25)
- Apply same `select()` trimming; include `cover_image_url` for future cards.

### `app/Http/Controllers/AdminBlogPostController.php`
- Handle cover upload/removal and tag sync (`$post->tags()->sync($tagIds)` via find-or-create by slug).
- `show()` (preview): canonical should be `route('blog.show', ...)` when status is published, else the preview URL (item #13).
- `edit()` passes `tags` (names string), `cover_image_url`, and posts list for a tag picker if desired.

---

## Phase 3 — Frontend (Inertia pages)

### `resources/views/app.blade.php`
- Echo schema prop (SSR-safe) right before `@inertiaHead`:

```blade
@if ($page['props']['schema'] ?? null)
    <script type="application/ld+json">{!! json_encode($page['props']['schema'], JSON_UNESCAPED_SLASHES) !!}</script>
@endif
```

### `resources/js/Pages/Blog/Show.jsx` (items #1, #2, #7, #9)
- `<Head>`: `og:image` + `og:image:alt`, `twitter:card` (`summary_large_image`), `twitter:title/description/image`, `article:published_time`.
- Render cover image at top of article when present.
- TOC component: sticky sidebar (lg+) or collapsed block before content — jump links to anchor ids.
- "Read next" section: 3 related post cards (reuse the card markup from Index).
- Breadcrumbs UI (Home / Blog / title) matching the BreadcrumbList schema.
- Byline uses `post.byline` (fallback already server-side).

### `resources/js/Pages/Blog/Index.jsx` (items #4, #6, #9)
- Accept new props: `canonical_url`, `page_title`, `search_query`, `heading` (for tag archive reuse).
- `<Head>`: canonical link, page-specific title.
- Hero card for the newest post (uses `cover_image`) when on page 1 with no search — needs `cover_image_url` in summary payload.
- Search form (`GET /blog?q=`) in the header block.
- Tag chips on cards linking to `/blog/tag/{slug}` when tags present.

### New: `resources/js/Components/BlogPostCard.jsx`
- Extract the card from Index so Index, tag archive, and "Read next" share one component.

### `resources/js/Pages/Blog/Admin/Edit.jsx` (items #1, #6)
- Cover image: file input with current-image preview + "remove" checkbox; switch `useForm` to `forceFormData` on submit when a file is chosen.
- Tags: comma-separated input, live chip preview.
- Byline input.
- Optional but cheap SERP preview (title/description length meters using existing `seo_title`/`meta_description` fields).
- Reading time shown as static text (server-computed).

---

## Phase 4 — Feed, sitemap, headers

### `resources/views/blog/feed.blade.php` (item #8)
- Add `<atom:link rel="self" href="{{ route('blog.feed') }}" xmlns:atom="...">` on the channel.
- Add `<content:encoded>` (CDATA with full `body_html`) — needs `xmlns:content` on `<rss>`.
- `<enclosure>` or `media:content` for `cover_image_url`; `<category>` per tag.
- Update `BlogController::feed()` to include `body_html`, cover URL, and loaded tags (limit 20 → consider 50 with content).

### `resources/views/blog/sitemap.blade.php`
- Add tag archive `<url>` entries (`route('blog.tag')`), each with `lastmod` = latest post `updated_at` in that tag.
- Keep blog index + posts as-is. `robots.txt` already points here — no change needed.

### Cache headers (Phase 4 of audit — public blog pages)
- Add `Cache-Control: public, max-age=300, stale-while-revalidate=600` to `blog.index`, `blog.show`, `blog.tag` responses (controller or a thin `CachePublicBlogPages` middleware scoped to those route names). Do **not** apply to preview/admin. Revisit ETag/`If-None-Match` only if traffic justifies it.

### Slug restore edge (item #14)
- On `restoring`, check slug collision against `withTrashed()` and auto-suffix via existing `uniqueSlug()` logic. One small model hook + test.

---

## Phase 5 — Tests (extend existing suite)

| Area | Test file | Covers |
|---|---|---|
| Meta/OG/schema | `tests/Feature/BlogSsrTest.php` (extend) | og:image, twitter card, JSON-LD Article + Breadcrumb in rendered HTML, canonical on page 1 vs page N |
| Meta fallback | `tests/Feature/BlogTest.php` (extend) | `metaDescription()` limits to ≤160 chars when falling back to excerpt |
| Related posts | new `tests/Feature/BlogRelatedPostsTest.php` | shared-tag ordering, excludes self, max 3, published only |
| Tags/archive | new `tests/Feature/BlogTagsTest.php` | tag route 200, canonical/meta, factory tags, sync on update |
| Cover image | new `tests/Feature/BlogCoverImageTest.php` | upload validation (mime/size/url-or-file), URL passthrough, removal, public disk storage |
| Save-hook gating | `tests/Unit/…` or feature | markdown re-render only when body dirty; reading time updated on body edit, stable on title edit |
| Select columns | `tests/Feature/BlogTest.php` | `assertQueryCount`-style guard or query-log assertion that index doesn't select `body_html`/`body_markdown` |
| Pagination SEO | `tests/Feature/BlogSsrTest.php` | page 2 title contains "Page 2", canonical carries page param |
| Feed | new assertion in `BlogTest.php` | `content:encoded`, categories, atom:link present |
| Sitemap | existing sitemap test if any | tag URLs present |
| Restore slug | `tests/Feature/BlogTest.php` | restore with colliding slug gets suffixed |
| Search | new `tests/Feature/BlogSearchTest.php` | `?q=` filters results (SQLite LIKE path), empty → noindex |

JS: extend `resources/js/lib/blogProfitability.js` tests' conventions for any new card component logic (pure functions only).

---

## Implementation order & effort

| Phase | Ships | Est. effort |
|---|---|---|
| 0 | Migrations + factories | 0.5 day |
| 1 | Model/requests/upload backend | 1 day |
| 2 | Controllers, processor, tag routes, search | 1.5 days |
| 3 | All React page changes + admin UX | 1.5 days |
| 4 | Feed/sitemap/headers/restore hook | 0.5 day |
| 5 | Test suite pass + fixes | 1 day |
| — | **Total** | **~6 days** |

Recommended merge points: end of Phase 1 (backend green), end of Phase 3 (user-visible), end of Phase 5 (complete).

## Out of scope (flagged, not planned)
- CDN/image resizing pipeline (imgix-style transformations, multiple srcset sizes) — do when cover images exist in prod.
- Multi-author pages (`/blog/author/{name}`) — only if bylines become real user profiles.
- Editorial workflow (review states) — current draft/scheduled/published is sufficient.
- Comments/engagement — product decision, not SEO.
