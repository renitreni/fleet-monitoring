{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="http://search.yahoo.com/mrss/">
    <channel>
        <title>{{ config('app.name') }} Blog</title>
        <link>{{ route('blog.index') }}</link>
        <description>Practical car care, maintenance, and road-trip guidance from {{ config('app.name') }}.</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ route('blog.show', $post->slug) }}</link>
                <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
                <description>{{ $post->excerpt }}</description>
                <content:encoded><![CDATA[{!! $post->body_html !!}]]></content:encoded>
                @foreach ($post->tags as $tag)
                    <category>{{ $tag->name }}</category>
                @endforeach
                @if ($post->coverImageUrl())
                    <media:content url="{{ $post->coverImageUrl() }}" medium="image" />
                @endif
                <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
            </item>
        @endforeach
    </channel>
</rss>
