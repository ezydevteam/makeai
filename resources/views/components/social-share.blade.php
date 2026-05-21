@php
    $labels = \App\Services\SocialService::SHARE_NETWORKS;
    $style = $payload['style'] ?? 'icon-label';
    $showCounts = (bool) ($payload['show_counts'] ?? false);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }} aria-label="{{ translate('Social share buttons') }}">
    @foreach ($payload['networks'] ?? [] as $network)
        @continue($network === 'copy')

        @php
            $label = $labels[$network] ?? ucfirst((string) $network);
            $shareUrl = $payload['urls'][$network] ?? null;
            $count = $payload['counts'][$network] ?? null;
        @endphp

        @if ($shareUrl)
            <a
                href="{{ $shareUrl }}"
                target="{{ $network === 'email' ? '_self' : '_blank' }}"
                rel="{{ $network === 'email' ? '' : 'noopener noreferrer' }}"
                title="{{ translate('Share on :network', ['network' => translate($label)]) }}"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:border-primary-500 hover:bg-primary-500 hover:text-white"
            >
                <span aria-hidden="true">{{ strtoupper(substr(translate($label), 0, 1)) }}</span>
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
