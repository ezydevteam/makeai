<!DOCTYPE html>
@php
    $isRtl = in_array(data_get($page, 'props.locale.is_rtl'), [true, 1, '1'], true);
    $presetService = app(\App\Services\ThemeSettingsService::class);
    $themeSettings = $presetService->getResolvedFrontendTheme();
    $customCodeSettings = $presetService->getStoredCustomCodeSettings();
    $themeCssTimestamp = \App\Models\Setting::query()
        ->whereIn('key', [
            'frontend_theme_settings',
            'frontend_custom_code',
        ])
        ->max('updated_at')?->timestamp ?? time();
    $selectedFonts = array_unique(array_filter([
        $themeSettings['font_body'] ?? 'Inter',
        $themeSettings['heading_font'] ?? 'Plus Jakarta Sans',
        'Inter',
        'Plus Jakarta Sans',
    ]));
    $systemFonts = ['system-ui', 'Arial', 'Georgia', 'serif', 'sans-serif'];
    $fontFamilies = collect($selectedFonts)
        ->reject(fn ($font) => in_array($font, $systemFonts, true))
        ->map(function ($font) {
            $slug = \Illuminate\Support\Str::of($font)
                ->trim()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '-')
                ->trim('-');

            return "{$slug}:300,400,500,600,700,800";
        })
        ->unique()
        ->implode('|');
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
    @if($fontFamilies !== '')
        <link href="https://fonts.bunny.net/css?family={{ $fontFamilies }}" rel="stylesheet" />
    @endif

    <link rel="stylesheet" href="{{ route('theme-variables.css') }}?v={{ $themeCssTimestamp }}" />

    @routes
    @if(settings('ads_auto_ads_enabled', false) && settings('adsense_publisher_id'))
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ settings('adsense_publisher_id') }}" crossorigin="anonymous"></script>
    @endif
    @php
        $component = $page['component'] ?? '';
        if ($component && str_starts_with($component, 'Addons/')) {
            $viteEntries = ['resources/js/app.ts'];
        } else {
            $activeTheme = settings('active_theme', 'default');
            $themePath = "resources/themes/{$activeTheme}/js/{$component}.vue";

            if (file_exists(base_path($themePath))) {
                $viteEntries = ['resources/js/app.ts', $themePath];
            } else {
                $viteEntries = ['resources/js/app.ts', "resources/js/Pages/{$component}.vue"];
            }
        }
    @endphp
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
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
    {!! $customCodeSettings['custom_footer_code'] ?? '' !!}
</body>
</html>
