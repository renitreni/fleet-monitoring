{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0">
    <channel>
        <title>{{ config('app.name') }} Blog</title>
        <link>{{ route('blog.index') }}</link>
        <description>Practical car care, maintenance, and road-trip guidance from {{ config('app.name') }}.</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ route('blog.show', $post->slug) }}</link>
                <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
                <description>{{ $post->excerpt }}</description>
                <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
            </item>
        @endforeach
    </channel>
</rss>
