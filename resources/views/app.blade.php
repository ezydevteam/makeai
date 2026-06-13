<!DOCTYPE html>
@php
    $isRtl = in_array(data_get($page, 'props.locale.is_rtl'), [true, 1, '1'], true);
@endphp
<html lang="{{ str_replace('_', '-', data_get($page, 'props.locale.code', app()->getLocale())) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="{{ $isRtl ? 'rtl' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ translate(':app — One platform. Every AI tool.', ['app' => settings('site_name')]) }}">

    @if(settings('site_favicon_png'))
        <link rel="icon" type="image/png" href="{{ Storage::disk('public')->url(settings('site_favicon_png')) }}">
    @endif
    @if(settings('site_favicon_ico'))
        <link rel="alternate icon" type="image/x-icon" href="{{ Storage::disk('public')->url(settings('site_favicon_ico')) }}">
    @endif

    <title inertia>{{ settings('site_name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800|plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />

    <link rel="stylesheet" href="{{ route('theme-variables.css') }}" />

    @routes
    @if(settings('ads_auto_ads_enabled', false) && settings('adsense_publisher_id'))
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ settings('adsense_publisher_id') }}" crossorigin="anonymous"></script>
    @endif
    @php
        $component = $page['component'] ?? '';
        if ($component && str_starts_with($component, 'Addons/')) {
            $viteEntries = ['resources/js/app.ts'];
        } else {
            $viteEntries = ['resources/js/app.ts', "resources/js/Pages/{$component}.vue"];
        }
    @endphp
    @vite($viteEntries)
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
