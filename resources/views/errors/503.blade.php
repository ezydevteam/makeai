@php
    $appName = settings('app_name', 'Application');
    $primaryColor = settings('primary_color', '#8b5cf6');
    $logoUrl = settings('app_logo_light') ? Storage::url(settings('app_logo_light')) : null;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ translate('Under Maintenance') }} — {{ $appName }}</title>
    <style>
        :root {
            --p: {{ $primaryColor }};
            --bg: #f5f3ff; --card: #fff;
            --border: #ddd6fe; --t1: #1e1b4b; --t2: #4b5563;
            --radius: 16px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: grid; place-items: center; padding: 24px;
            background: linear-gradient(135deg, #f5f3ff, #eef2ff);
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
        p { font-size: 15px; line-height: 1.65; margin-bottom: 8px; }
        .footer { margin-top: 28px; font-size: 13px; color: #9ca3af; }
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
    <div class="code">503</div>
    <h1>{{ translate('Under maintenance') }}</h1>
    <p>{{ settings('maintenance_message', translate("We're making improvements to the platform. Please check back shortly.")) }}</p>
    <p class="footer">{{ translate('Thank you for your patience.') }}</p>
</main>
</body>
</html>
