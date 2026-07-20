@php
    $labels = \App\Services\SocialService::SHARE_NETWORKS;
    $style = $payload['style'] ?? 'icon-label';
    $showCounts = (bool) ($payload['show_counts'] ?? false);
    $networkIcons = [
        'facebook' => '<path d="M13.5 22v-8h2.7l.4-3h-3.1V9.1c0-.9.2-1.5 1.5-1.5h1.7V4.9c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1V11H7.6v3h2.7v8h3.2Z"/>',
        'x' => '<path d="M18.2 2.3h3.3l-7.2 8.2 8.5 11.2h-6.7l-5.2-6.8-6 6.8H1.7l7.7-8.8L1.3 2.3h6.9l4.7 6.2 5.3-6.2Zm-1.2 17.5h1.8L7.1 4.1H5.1L17 19.8Z"/>',
        'linkedin' => '<path d="M6.9 20.5H3.5V9h3.4v11.5ZM5.2 7.4a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm15.3 13.1h-3.4v-5.6c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9v5.7H9.8V9h3.3v1.6h.1c.5-.9 1.6-1.9 3.4-1.9 3.6 0 4.2 2.4 4.2 5.5v6.3Z"/>',
        'whatsapp' => '<path d="M20.5 3.5A11.8 11.8 0 0 0 12.1 0C5.5 0 .2 5.3.2 11.9c0 2.1.5 4.1 1.6 5.9L.1 24l6.3-1.7a11.9 11.9 0 0 0 5.7 1.5c6.6 0 11.9-5.3 11.9-11.9 0-3.2-1.2-6.2-3.5-8.4Zm-8.4 18.3c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4a9.8 9.8 0 0 1-1.5-5.3c0-5.4 4.4-9.9 9.9-9.9 2.6 0 5.1 1 7 2.9a9.8 9.8 0 0 1 2.9 7c0 5.5-4.5 9.9-10 9.9Zm5.4-7.4c-.3-.1-1.8-.9-2-.9-.3-.1-.5-.2-.7.1-.2.3-.8 1-.9 1.2-.2.2-.4.2-.7.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2c-.2-.3 0-.5.1-.6l.5-.5.3-.5c.1-.2.1-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.4Z"/>',
        'telegram' => '<path d="M21.9 4.2 18.7 19c-.2 1-.8 1.2-1.6.8l-4.8-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.4-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.6 12.8l-4.7-1.5c-1-.3-1-1 .2-1.4L20.4 2.8c.9-.3 1.7.2 1.5 1.4Z"/>',
        'pinterest' => '<path d="M12.2 0C5.5 0 2.1 4.8 2.1 8.8c0 2.4.9 4.6 2.9 5.4.3.1.6 0 .7-.4l.3-1.2c.1-.4.1-.5-.2-.9-.6-.7-1-1.6-1-2.8 0-3.6 2.7-6.8 7-6.8 3.8 0 5.9 2.3 5.9 5.4 0 4.1-1.8 7.5-4.5 7.5-1.5 0-2.6-1.2-2.2-2.7.4-1.8 1.3-3.8 1.3-5.1 0-1.2-.6-2.2-2-2.2-1.6 0-2.8 1.6-2.8 3.8 0 1.4.5 2.3.5 2.3l-1.9 8c-.6 2.4-.1 5.3 0 5.6 0 .2.3.2.4.1.2-.2 2.6-3.2 3.4-6.1l1-3.7c.5.9 1.8 1.7 3.2 1.7 4.2 0 7-3.8 7-8.9C21.1 3.9 17.8 0 12.2 0Z"/>',
        'reddit' => '<path d="M24 11.8a2.7 2.7 0 0 0-4.6-1.9 13.3 13.3 0 0 0-6.5-2l1.1-5 3.5.8a2.2 2.2 0 1 0 .3-1.4l-4.2-.9a.7.7 0 0 0-.8.5l-1.3 6a13.4 13.4 0 0 0-6.9 2 2.7 2.7 0 1 0-3 4.4c0 .2-.1.5-.1.8 0 4 4.7 7.2 10.5 7.2s10.5-3.2 10.5-7.2c0-.3 0-.6-.1-.8 1-.5 1.6-1.4 1.6-2.5ZM7.5 14a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm8.1 4.3c-1 .9-2.3 1.3-3.6 1.3s-2.7-.4-3.6-1.3a.7.7 0 0 1 1-1c.6.6 1.6.9 2.6.9s1.9-.3 2.6-.9a.7.7 0 0 1 1 1Zm.9-2.8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/>',
        'email' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 8 9 6 9-6"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 6h14a2 2 0 0 1 2 2"/>',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }} aria-label="{{ translate('Social share buttons') }}">
    @foreach ($payload['networks'] ?? [] as $network)
        @continue($network === 'copy')

        @php
            $label = $labels[$network] ?? ucfirst((string) $network);
            $shareUrl = $payload['urls'][$network] ?? null;
            $count = $payload['counts'][$network] ?? null;
            $isEmail = $network === 'email';
            $icon = $networkIcons[$network] ?? '<path stroke-linecap="round" stroke-linejoin="round" d="M13.2 18.4a3.5 3.5 0 0 0 3.4-2.4A3.5 3.5 0 0 0 15 10.7l-1.7.7a3 3 0 0 1-3.7-1.8 3 3 0 0 1 1.8-3.7l.5-.2a4 4 0 0 0-3.2-2.1"/>';
        @endphp

        @if ($shareUrl)
            <a
                href="{{ $shareUrl }}"
                target="{{ $isEmail ? '_self' : '_blank' }}"
                rel="{{ $isEmail ? '' : 'noopener noreferrer' }}"
                title="{{ translate('Share on :network', ['network' => translate($label)]) }}"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:border-primary-500 hover:bg-primary-500 hover:text-white"
            >
                <span class="inline-flex h-5 w-5 items-center justify-center" aria-hidden="true">
                    <svg class="h-4 w-4" fill="{{ $isEmail ? 'none' : 'currentColor' }}" viewBox="0 0 24 24" stroke="{{ $isEmail ? 'currentColor' : 'none' }}" stroke-width="2.25">{!! $icon !!}</svg>
                </span>
                @if ($style === 'icon-label')
                    <span>{{ translate($label) }}</span>
                @endif
                @if ($style === 'icon-count' && $showCounts && $count !== null)
                    <span class="text-xs">{{ $count }}</span>
                @endif
            </a>
        @endif
    @endforeach
</div>
