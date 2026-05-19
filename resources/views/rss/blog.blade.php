{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ route('blog.index') }}</link>
        <atom:link href="{{ route('blog.rss') }}" rel="self" type="application/rss+xml" />
        <description>{{ $description }}</description>
        <lastBuildDate>{{ $updated }}</lastBuildDate>
        <language>{{ app()->getLocale() }}</language>
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ route('blog.show', $post->slug) }}</link>
                <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
                <description><![CDATA[{!! $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 180) !!}]]></description>
                <pubDate>{{ optional($post->published_at)->toRfc2822String() }}</pubDate>
            </item>
        @endforeach
    </channel>
</rss>
