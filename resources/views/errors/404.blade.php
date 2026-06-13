@php
    $appName = settings('app_name', 'Application');
    $primaryColor = settings('primary_color', '#6366f1');
    $logoUrl = settings('app_logo_light') ? Storage::url(settings('app_logo_light')) : null;
    $pageTitle = 'Page Not Found';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $pageTitle }} — {{ $appName }}</title>
    <style>
        :root {
            --p: {{ $primaryColor }};
            --bg: #f8f7ff; --card: #fff;
            --border: #e0e7ff; --t1: #1e1b4b; --t2: #4b5563;
            --radius: 16px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: grid; place-items: center; padding: 24px;
            background: linear-gradient(135deg, #f8f7ff, #f0f5ff);
            color: var(--t2); font-family: Inter, system-ui, sans-serif;
        }
        .panel {
            width: min(560px, 100%); border: 1px solid var(--border);
            border-radius: var(--radius); background: var(--card);
            box-shadow: 0 20px 60px color-mix(in srgb, var(--p) 8%, transparent); padding: 40px 36px; text-align: center;
        }
        .brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 32px; }
        .logo-img { max-height: 36px; max-width: 160px; object-fit: contain; }
        .logo-fallback { background: var(--p); color: #fff; font-weight: 700; font-size: 16px;
            width: 40px; height: 40px; border-radius: 10px; display: inline-grid; place-items: center; }
        .code { font-size: 72px; font-weight: 800; color: var(--p); line-height: 1; margin-bottom: 8px; }
        h1 { font-size: clamp(22px,4vw,28px); font-weight: 700; color: var(--t1); margin-bottom: 12px; }
        p { font-size: 15px; line-height: 1.65; margin-bottom: 28px; }
        .actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 11px 22px; border-radius: 12px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.15s; cursor: pointer; border: none; }
        .btn-primary { background: var(--p); color: #fff; }
        .btn-primary:hover { filter: brightness(0.92); }
        .btn-outline { background: #fff; color: var(--p); border: 1.5px solid var(--border); }
        .btn-outline:hover { background: color-mix(in srgb, var(--p) 6%, transparent); }
        @media (max-width: 480px) { .panel { padding: 28px 20px; } .code { font-size: 56px; } }
    </style>
</head>
<body>
<main class="panel">
    <div class="brand">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="logo-img">
        @else
            <div class="logo-fallback">{{ Str::of($appName)->substr(0, 2)->upper() }}</div>
        @endif
    </div>
    <div class="code">404</div>
    <h1>{{ translate('Page not found') }}</h1>
    <p>{{ translate("The page you're looking for doesn't exist or has been moved. Let's get you back on track.") }}</p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">🏠 {{ translate('Go to Homepage') }}</a>
        <a href="{{ url('/ai-tools') }}" class="btn btn-outline">🔍 {{ translate('Search Tools') }}</a>
    </div>
</main>
</body>
</html>
