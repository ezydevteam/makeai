<?php

declare(strict_types=1);

namespace App\Services;

class WebAnalyticsService
{
    private bool $enabled;
    private string $provider;
    private bool $respectConsent;
    private ?string $ga4MeasurementId;
    private ?string $gtmContainerId;
    private ?string $plausibleDomain;
    private ?string $plausibleScriptUrl;
    private ?string $umamiWebsiteId;
    private ?string $umamiScriptUrl;

    public function __construct(
        bool $enabled = false,
        string $provider = 'ga4',
        bool $respectConsent = true,
        ?string $ga4MeasurementId = null,
        ?string $gtmContainerId = null,
        ?string $plausibleDomain = null,
        ?string $plausibleScriptUrl = null,
        ?string $umamiWebsiteId = null,
        ?string $umamiScriptUrl = null
    ) {
        $this->enabled = $enabled;
        $this->provider = $provider;
        $this->respectConsent = $respectConsent;
        $this->ga4MeasurementId = $ga4MeasurementId;
        $this->gtmContainerId = $gtmContainerId;
        $this->plausibleDomain = $plausibleDomain;
        $this->plausibleScriptUrl = $plausibleScriptUrl;
        $this->umamiWebsiteId = $umamiWebsiteId;
        $this->umamiScriptUrl = $umamiScriptUrl;
    }

    public static function fromSettings(): self
    {
        $provider = (string) settings('external_web_analytics_provider', 'ga4');
        $enabled = (bool) settings('external_web_analytics_enabled', false);
        $respectConsent = (bool) settings('external_web_analytics_respect_consent', true);

        $ga4MeasurementId = self::stringOrNull(settings('external_web_analytics_ga4_measurement_id'));
        $gtmContainerId = self::stringOrNull(settings('external_web_analytics_gtm_container_id'));
        $plausibleDomain = self::stringOrNull(settings('external_web_analytics_plausible_domain'));
        $plausibleScriptUrl = self::stringOrNull(settings('external_web_analytics_plausible_script_url', 'https://plausible.io/js/script.js'));
        $umamiWebsiteId = self::stringOrNull(settings('external_web_analytics_umami_website_id'));
        $umamiScriptUrl = self::stringOrNull(settings('external_web_analytics_umami_script_url'));

        return new self(
            $enabled,
            $provider,
            $respectConsent,
            $ga4MeasurementId,
            $gtmContainerId,
            $plausibleDomain,
            $plausibleScriptUrl,
            $umamiWebsiteId,
            $umamiScriptUrl
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return match ($this->provider) {
            'ga4' => filled($this->ga4MeasurementId),
            'gtm' => filled($this->gtmContainerId),
            'plausible' => filled($this->plausibleDomain),
            'umami' => filled($this->umamiWebsiteId),
            default => false,
        };
    }

    /**
     * Whether analytics may load for the current request.
     *
     * Always true when consent-gating is off or site-wide cookie consent is
     * disabled. Otherwise the visitor's cookie_consent cookie (written by the
     * consent banner, kept plaintext via encryptCookies except-list) must grant
     * the `analytics` category. Absent/undecided consent → false (no tracking
     * before an explicit choice).
     */
    public function hasAnalyticsConsent(): bool
    {
        if (! $this->respectConsent || ! (bool) settings('gdpr_enabled', false)) {
            return true;
        }

        try {
            $raw = request()->cookie('cookie_consent');
        } catch (\Throwable) {
            return false;
        }

        if (! is_string($raw) || $raw === '') {
            return false;
        }

        $consent = json_decode($raw, true);

        return is_array($consent) && ($consent['analytics'] ?? false) === true;
    }

    /**
     * Build the <script> tag(s) for the active analytics provider.
     *
     * GDPR: when "respect consent" is on and cookie consent is enabled site-wide,
     * this returns '' until the visitor's cookie_consent cookie grants analytics —
     * so no tracking script loads before consent. {@see hasAnalyticsConsent()}.
     */
    public function headSnippet(): string
    {
        if (! $this->isEnabled() || ! $this->hasAnalyticsConsent()) {
            return '';
        }

        return match ($this->provider) {
            'ga4' => $this->ga4Snippet(),
            'gtm' => $this->gtmSnippet(),
            'plausible' => $this->plausibleSnippet(),
            'umami' => $this->umamiSnippet(),
            default => '',
        };
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => translate('Web analytics is not fully configured.'),
            ];
        }

        return [
            'success' => true,
            'message' => translate(':provider tracking ID configured.', [
                'provider' => $this->providerLabel(),
            ]),
        ];
    }

    private function ga4Snippet(): string
    {
        $id = e((string) $this->ga4MeasurementId);
        $idJs = $this->jsString((string) $this->ga4MeasurementId);

        return <<<HTML
        <script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', {$idJs});
        </script>
        HTML;
    }

    private function gtmSnippet(): string
    {
        $idJs = $this->jsString((string) $this->gtmContainerId);

        return <<<HTML
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer',{$idJs});</script>
        HTML;
    }

    private function plausibleSnippet(): string
    {
        $domain = e((string) $this->plausibleDomain);
        $scriptUrl = e((string) ($this->plausibleScriptUrl ?: 'https://plausible.io/js/script.js'));

        return <<<HTML
        <script defer data-domain="{$domain}" src="{$scriptUrl}"></script>
        HTML;
    }

    private function umamiSnippet(): string
    {
        $websiteId = e((string) $this->umamiWebsiteId);
        $scriptUrl = e((string) $this->umamiScriptUrl);

        return <<<HTML
        <script defer src="{$scriptUrl}" data-website-id="{$websiteId}"></script>
        HTML;
    }

    /**
     * Encode a raw value into a JSON string literal safe for direct
     * interpolation inside an inline <script> block (escapes HTML-sensitive
     * characters so the value cannot break out of the script context).
     */
    private function jsString(string $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?: '""';
    }

    private function providerLabel(): string
    {
        return match ($this->provider) {
            'ga4' => 'Google Analytics 4',
            'gtm' => 'Google Tag Manager',
            'plausible' => 'Plausible',
            'umami' => 'Umami',
            default => ucfirst($this->provider),
        };
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
