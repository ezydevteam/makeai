@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $appName = settings('app_name', translate('Application'));
    $title = settings('maintenance_title', $appName.' '.translate('Maintenance'));
    $message = strip_tags(
        (string) settings('maintenance_message', '<p>'.translate('We are improving the platform. Please check back soon.').'</p>'),
        '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h2><h3><h4><a>'
    );
    $restorationTime = settings('maintenance_estimated_restoration_time');
    $restorationIso = null;
    $backgroundImage = settings('maintenance_background_image');
    $backgroundUrl = $backgroundImage ? Storage::url($backgroundImage) : null;

    try {
        $restorationIso = filled($restorationTime) ? Carbon::parse($restorationTime)->toIso8601String() : null;
    } catch (Throwable) {
        $restorationIso = null;
    }
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} - {{ $appName }}</title>
    <style>
        :root {
            --color-primary-500: #10b981;
            --color-primary-600: #059669;
            --color-secondary-500: #3b82f6;
            --color-gray-50: #f9fafb;
            --color-gray-100: #f3f4f6;
            --color-gray-500: #6b7280;
            --color-gray-700: #374151;
            --color-gray-900: #111827;
            --surface-bg: #f0fdf8;
            --surface-card: rgba(255, 255, 255, 0.9);
            --border-color: #d1fae5;
            --radius-lg: 12px;
            --font-heading: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
            --font-body: Inter, ui-sans-serif, system-ui, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: var(--surface-bg);
            color: var(--color-gray-700);
            font-family: var(--font-body);
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background-image: linear-gradient(135deg, rgba(240, 253, 248, 0.92), rgba(239, 246, 255, 0.9)){{ $backgroundUrl ? ", url('".e($backgroundUrl)."')" : '' }};
            background-position: center;
            background-size: cover;
        }

        .panel {
            width: min(720px, 100%);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            background: var(--surface-card);
            box-shadow: 0 24px 70px rgba(15, 31, 23, 0.14);
            padding: 32px;
            backdrop-filter: blur(12px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .logo {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--color-primary-500), var(--color-secondary-500));
            color: white;
            font-weight: 700;
        }

        .brand-name {
            color: var(--color-gray-900);
            font-family: var(--font-heading);
            font-size: 18px;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            color: var(--color-gray-900);
            font-family: var(--font-heading);
            font-size: clamp(28px, 5vw, 42px);
            line-height: 1.08;
        }

        .message {
            margin-top: 18px;
            font-size: 16px;
            line-height: 1.7;
        }

        .message :first-child {
            margin-top: 0;
        }

        .message :last-child {
            margin-bottom: 0;
        }

        .countdown {
            display: none;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 28px;
        }

        .countdown.is-visible {
            display: grid;
        }

        .time-box {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: white;
            padding: 14px 10px;
            text-align: center;
        }

        .time-box strong {
            display: block;
            color: var(--color-primary-600);
            font-family: var(--font-heading);
            font-size: 24px;
        }

        .time-box span {
            color: var(--color-gray-500);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .footer {
            margin-top: 28px;
            color: var(--color-gray-500);
            font-size: 13px;
        }

        @media (max-width: 520px) {
            .panel {
                padding: 24px;
            }

            .countdown {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="panel" aria-labelledby="maintenance-title">
            <div class="brand">
                <div class="logo">{{ Str::of($appName)->substr(0, 1)->upper() }}</div>
                <div class="brand-name">{{ $appName }}</div>
            </div>

            <h1 id="maintenance-title">{{ $title }}</h1>
            <div class="message">{!! $message !!}</div>

            @if ($restorationIso)
                <div class="countdown" data-countdown="{{ $restorationIso }}">
                    <div class="time-box"><strong data-days>0</strong><span>{{ translate('Days') }}</span></div>
                    <div class="time-box"><strong data-hours>0</strong><span>{{ translate('Hours') }}</span></div>
                    <div class="time-box"><strong data-minutes>0</strong><span>{{ translate('Minutes') }}</span></div>
                    <div class="time-box"><strong data-seconds>0</strong><span>{{ translate('Seconds') }}</span></div>
                </div>
            @endif

            <p class="footer">{{ translate('Thank you for your patience.') }}</p>
        </section>
    </main>

    @if ($restorationIso)
        <script>
            (() => {
                const element = document.querySelector('[data-countdown]');
                if (!element) return;

                const target = new Date(element.dataset.countdown).getTime();
                const days = element.querySelector('[data-days]');
                const hours = element.querySelector('[data-hours]');
                const minutes = element.querySelector('[data-minutes]');
                const seconds = element.querySelector('[data-seconds]');

                const update = () => {
                    const distance = Math.max(0, target - Date.now());
                    const dayValue = Math.floor(distance / 86400000);
                    const hourValue = Math.floor((distance % 86400000) / 3600000);
                    const minuteValue = Math.floor((distance % 3600000) / 60000);
                    const secondValue = Math.floor((distance % 60000) / 1000);

                    days.textContent = String(dayValue);
                    hours.textContent = String(hourValue).padStart(2, '0');
                    minutes.textContent = String(minuteValue).padStart(2, '0');
                    seconds.textContent = String(secondValue).padStart(2, '0');
                    element.classList.add('is-visible');
                };

                update();
                window.setInterval(update, 1000);
            })();
        </script>
    @endif
</body>
</html>
