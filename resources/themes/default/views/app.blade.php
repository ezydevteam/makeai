<!DOCTYPE html>
{{-- $isRtl, $themeSettings, $customCodeSettings, $themeCssTimestamp, $fontFamilies
     and $viteEntries are supplied by App\View\Composers\AppHeadComposer. --}}
<html lang="{{ str_replace('_', '-', data_get($page, 'props.locale.code', app()->getLocale())) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- ─── SEO head (server-rendered) ───────────────────────────────────────────
         Rendered here in the raw HTML so crawlers and social scrapers (which don't
         run JS) see per-page title/description/canonical/OG/Twitter/JSON-LD without
         a Node SSR process. Public controllers pass a normalized `seo` prop; other
         pages fall back to the site defaults below. Private pages get noindex. --}}
    @php
        $seo          = data_get($page, 'props.seo', []);
        $seoDesc      = data_get($seo, 'description') ?: settings('site_description')
                            ?: translate(':app — One platform. Every AI tool.', ['app' => settings('site_name')]);
        $seoCanonical = data_get($seo, 'canonical');
        $seoKeywords  = data_get($seo, 'keywords');
        $og           = data_get($seo, 'og', []);
        $tw           = data_get($seo, 'twitter', []);
        $seoSchemas   = data_get($seo, 'schemas', []);
        $ogImage      = data_get($og, 'image') ?: media_url(settings('site_og_image', ''));
        // Social scrapers require absolute image URLs; media_url() is root-relative on the local disk.
        if ($ogImage && \Illuminate\Support\Str::startsWith($ogImage, '/')) {
            $ogImage = rtrim(config('app.url'), '/') . $ogImage;
        }
        $ogTitle      = data_get($og, 'title') ?: (data_get($seo, 'title') ?: settings('site_name'));
        $ogDesc       = data_get($og, 'description') ?: $seoDesc;
        $ogUrl        = data_get($og, 'url') ?: url()->current();
        $ogType       = data_get($og, 'type', 'website');

        // og:locale wants the underscored form (en_US), unlike <html lang> above.
        $ogLocale     = str_replace('-', '_', data_get($page, 'props.locale.code', app()->getLocale()));

        // Image dimensions save scrapers a fetch, but only when we can read the real file:
        $ogImageSize = null; 
        if ($ogImage) {
            $relative = \Illuminate\Support\Str::after($ogImage, rtrim(config('app.url'), '/'));
            $localPath = public_path(ltrim($relative, '/'));
            if (\Illuminate\Support\Str::startsWith($ogImage, rtrim(config('app.url'), '/')) && is_file($localPath)) {
                $size = @getimagesize($localPath);
                $ogImageSize = $size ? [$size[0], $size[1]] : null;
            }
        }

        // Private/dynamic surfaces must never be indexed. A page can also force this
        // via seo.robots; otherwise it is derived from the request path.
        $privateRobots = request()->is(
            'admin', 'admin/*', 'horizon', 'horizon/*', 'dashboard', 'dashboard/*',
            'user', 'user/*', 'login', 'register', 'logout', 'password', 'password/*',
            'forgot-password', 'reset-password', 'reset-password/*', 'verify-email', 'verify-email/*',
            'two-factor', 'two-factor/*', 'settings', 'settings/*', 'profile', 'profile/*',
            'billing', 'billing/*', 'checkout', 'checkout/*', 'notifications', 'notifications/*',
            'onboarding', 'onboarding/*', 'history', 'favorites', 'collections', 'chains', 'chains/*'
        ) ? 'noindex, nofollow' : null;
        $seoRobots = data_get($seo, 'robots') ?: $privateRobots;
    @endphp

    <meta name="description" content="{{ $seoDesc }}">
    @if($seoKeywords)
    <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    @if($seoRobots)
    <meta name="robots" content="{{ $seoRobots }}">
    @endif
    @if($seoCanonical)
    <link rel="canonical" href="{{ $seoCanonical }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ settings('site_name') }}">
    <meta property="og:locale" content="{{ $ogLocale }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDesc }}">
    <meta property="og:url" content="{{ $ogUrl }}">
    @if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    {{-- Dimensions let scrapers lay out the card without fetching the image first;
         only emitted when the file is local and readable, never guessed. --}}
    @if($ogImageSize)
    <meta property="og:image:width" content="{{ $ogImageSize[0] }}">
    <meta property="og:image:height" content="{{ $ogImageSize[1] }}">
    @endif
    @endif

    {{-- Article timestamps: og:type=article only, so they never appear on website pages. --}}
    @if($ogType === 'article')
    @if(data_get($og, 'published_time'))
    <meta property="article:published_time" content="{{ data_get($og, 'published_time') }}">
    @endif
    @if(data_get($og, 'modified_time'))
    <meta property="article:modified_time" content="{{ data_get($og, 'modified_time') }}">
    @endif
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ data_get($tw, 'card', $ogImage ? 'summary_large_image' : 'summary') }}">
    <meta name="twitter:title" content="{{ data_get($tw, 'title') ?: $ogTitle }}">
    <meta name="twitter:description" content="{{ data_get($tw, 'description') ?: $ogDesc }}">
    @if(data_get($tw, 'image') ?: $ogImage)
    <meta name="twitter:image" content="{{ data_get($tw, 'image') ?: $ogImage }}">
    @endif

    @if(settings('site_favicon_png'))
    <link rel="icon" type="image/png" href="{{ Storage::disk('public')->url(settings('site_favicon_png')) }}">
    @endif
    @if(settings('site_favicon_ico'))
    <link rel="alternate icon" type="image/x-icon" href="{{ Storage::disk('public')->url(settings('site_favicon_ico')) }}">
    @endif

    <title inertia>{{ data_get($seo, 'title') ?: settings('site_name') }}</title>

    {{-- Fonts: loaded async so they never block first paint (A2.3/A4). display=swap
         renders text immediately in the fallback face, then swaps in the web font on
         load (no FOIT). The media="print"→"all" onload flip is the standard trick to
         make a stylesheet non-render-blocking; <noscript> keeps it working without JS. --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    @if($fontFamilies !== '')
    @php $fontHref = 'https://fonts.bunny.net/css?family='.$fontFamilies.'&display=swap'; @endphp
    <link rel="preload" as="style" href="{{ $fontHref }}">
    <link rel="stylesheet" href="{{ $fontHref }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ $fontHref }}"></noscript>
    @endif

    <link rel="stylesheet" href="{{ route('theme-variables.css') }}?v={{ $themeCssTimestamp }}" />

    @routes
    @if(settings('ads_auto_ads_enabled', false) && settings('adsense_publisher_id'))
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ settings('adsense_publisher_id') }}" crossorigin="anonymous"></script>
    @endif
    @vite($viteEntries)
    {{-- Addon pages load only the app bundle, whose @theme defaults would otherwise
         override the admin's dynamic theme palette (loaded above, before @vite).
         Re-load the theme variables AFTER the bundle so the brand primary wins on
         addon pages (KB, chatbot, …) too. Scoped to addons — theme pages unaffected. --}}
    @if(($page['component'] ?? '') && str_starts_with($page['component'], 'Addons/'))
    <link rel="stylesheet" href="{{ route('theme-variables.css') }}?v={{ $themeCssTimestamp }}" />
    @endif
    {!! $customCodeSettings['custom_header_code'] ?? '' !!}
    {!! \App\Services\WebAnalyticsService::fromSettings()->headSnippet() !!}

    {{-- Structured data (JSON-LD) rendered server-side for rich results. --}}
    @foreach($seoSchemas as $seoSchema)
    <script type="application/ld+json">{!! json_encode($seoSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
    {!! $customCodeSettings['custom_footer_code'] ?? '' !!}
</body>
</html>
