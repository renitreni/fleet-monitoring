{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('blog.index') }}</loc>
    </url>
    @foreach ($tags as $tag)
        @if ($tag->posts_max_updated_at)
            <url>
                <loc>{{ route('blog.tag', $tag->slug) }}</loc>
                <lastmod>{{ \Illuminate\Support\Carbon::parse($tag->posts_max_updated_at)->toAtomString() }}</lastmod>
            </url>
        @endif
    @endforeach
    @foreach ($posts as $post)
        <url>
            <loc>{{ route('blog.show', $post->slug) }}</loc>
            <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
