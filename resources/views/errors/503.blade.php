@php
    $appName = settings('app_name', 'Application');
    $primaryColor = settings('primary_color', '#8b5cf6');
    $logoUrl = media_url(settings('site_logo_light')) ?: null;
    $isAdminError = request()->is('admin') || request()->is('admin/*');
    $primaryActionUrl = $isAdminError ? route('admin.dashboard') : url('/');
    $primaryActionLabel = $isAdminError ? translate('Back to Dashboard') : translate('Go to Homepage');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ translate('Under Maintenance') }} - {{ $appName }}</title>
    <style>
        :root {
            --p: {{ $primaryColor }};
            --bg: #f5f3ff; --card: #fff;
            --border: #ddd6fe; --t1: #1e1b4b; --t2: #4b5563;
            --radius: 16px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #f5f3ff, #eef2ff); color: var(--t2); font-family: Inter, system-ui, sans-serif; }
        .page-shell {
            min-height: 100vh; min-height: 100dvh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }
        .panel {
            width: min(560px, 100%); border: 1px solid var(--border);
            border-radius: var(--radius); background: var(--card);
            box-shadow: 0 20px 60px color-mix(in srgb, var(--p) 8%, transparent); padding: 40px 36px; text-align: center;
        }
        .brand { display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 40px; margin-bottom: 32px; }
        .logo-img { display: block; height: 36px; max-width: 160px; object-fit: contain; }
        .logo-fallback { background: var(--p); color: #fff; font-weight: 700; font-size: 16px;
            width: 40px; height: 40px; border-radius: 10px; display: inline-grid; place-items: center; }
        .code {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.04em;
            min-height: 88px;
            font-size: 72px; font-weight: 800; color: var(--p); line-height: 1; margin-bottom: 8px;
            overflow: hidden;
        }
        .code-digit {
            display: inline-block;
            opacity: 0;
            will-change: transform, opacity;
        }
        .code-digit-first { animation: code-enter-left 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.08s forwards; }
        .code-digit-middle { animation: code-enter-top 0.58s cubic-bezier(0.22, 1, 0.36, 1) 0.24s forwards; }
        .code-digit-last { animation: code-enter-right 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.12s forwards; }
        @keyframes code-enter-left {
            from { opacity: 0; transform: translateX(-42px) rotate(-8deg) scale(0.92); }
            to { opacity: 1; transform: translateX(0) rotate(0) scale(1); }
        }
        @keyframes code-enter-right {
            from { opacity: 0; transform: translateX(42px) rotate(8deg) scale(0.92); }
            to { opacity: 1; transform: translateX(0) rotate(0) scale(1); }
        }
        @keyframes code-enter-top {
            from { opacity: 0; transform: translateY(-34px) scale(0.78); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        h1 { font-size: clamp(22px,4vw,28px); font-weight: 700; color: var(--t1); margin-bottom: 12px; }
        p { font-size: 15px; line-height: 1.65; margin-bottom: 8px; }
        .actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 11px 22px; border-radius: 12px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.15s; cursor: pointer; border: none; }
        .btn-primary { background: var(--p); color: #fff; }
        .btn-primary:hover { filter: brightness(0.92); }
        @media (max-width: 480px) { .panel { padding: 28px 20px; } .code { font-size: 56px; } }
    </style>
</head>
<body>
<div class="page-shell">
<main class="panel">
    <div class="brand">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="logo-img">
        @else
            <div class="logo-fallback">{{ Str::of($appName)->substr(0, 1)->upper() }}</div>
        @endif
    </div>
    <div class="code" aria-label="503">
        <span class="code-digit code-digit-first">5</span>
        <span class="code-digit code-digit-middle">0</span>
        <span class="code-digit code-digit-last">3</span>
    </div>
    <h1>{{ translate('Under maintenance') }}</h1>
    <p>{{ settings('maintenance_message', translate("We're making improvements to the platform. Please check back shortly.")) }}</p>
    <div class="actions">
        <a href="{{ $primaryActionUrl }}" class="btn btn-primary">🏠 {{ $primaryActionLabel }}</a>
    </div>
</main>
</div>
</body>
</html>
