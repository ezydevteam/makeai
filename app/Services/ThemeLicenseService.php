<?php

namespace App\Services;

use App\DTO\LicenseResult;
use App\Models\ThemeLicense;
use App\Support\LicenseKey;
use App\Support\PurchaseCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Per-theme license verification + update checks against the License Server
 * (product=theme). Mirrors AddonLicenseService but delegates signature handling to
 * LicenseService (single embedded key via App\Support\LicenseKey).
 */
class ThemeLicenseService
{
    private const CACHE_KEY_PREFIX = 'theme_license.';
    private const CACHE_TTL = 3600;

    /** First-time activation (or manual re-entry after invalidation). */
    public function verify(string $themeSlug, string $purchaseCode): LicenseResult
    {
        if (config('demo.enabled')) {
            return LicenseResult::failure(translate('Verification is disabled in demo mode.'), 'demo_mode');
        }

        // Test mode: fake codes activate without contacting the server.
        if (($testType = PurchaseCode::matchTestCode($purchaseCode)) !== null) {
            return $this->buildTestResult($themeSlug, $purchaseCode, $testType);
        }

        if (! PurchaseCode::isValidUuid($purchaseCode)) {
            return LicenseResult::failure(translate('Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'), 'invalid_format');
        }

        if (! $this->publicKeyConfigured()) {
            Log::critical('ThemeLicenseService: License Server public key (App\Support\LicenseKey) is still the placeholder — real key not set before packaging.');
            return LicenseResult::failure(translate('License verification is not configured on this build. Please contact the author/support.'), 'public_key_missing');
        }

        $ls = app(LicenseService::class);
        $version = app(ThemeService::class)->getThemeConfig($themeSlug)['version'] ?? '1.0.0';

        try {
            $response = Http::timeout(15)->retry(2, 1000)->post($ls->verifyEndpoint(), [
                'product' => 'theme',
                'slug' => $themeSlug,
                'purchase_code' => $purchaseCode,
                'domain' => request()->getHost(),
                'version' => $version,
            ]);
        } catch (\Throwable $e) {
            Log::error('ThemeLicenseService: connection failed', ['theme' => $themeSlug, 'error' => $e->getMessage()]);
            return LicenseResult::failure(translate('Could not connect to license server — please try again.'), 'connection_error');
        }

        if (! $response->successful()) {
            return LicenseResult::failure(translate('Could not reach license server — try again.'), 'network');
        }

        $data = (array) $response->json();
        $payload = $ls->verifySignedResponse($response->body(), $data);
        if ($payload === null) {
            return LicenseResult::failure(translate('Could not verify license server response.'), 'signature_invalid');
        }

        if (empty($payload['valid'])) {
            $errorMap = [
                'invalid_format' => translate('Invalid purchase code format.'),
                'not_found' => translate('Purchase code not found.'),
                'wrong_item' => translate('This code belongs to a different item.'),
                'refunded' => translate('This purchase was refunded.'),
                'revoked' => translate('This license has been revoked.'),
                'envato_error' => translate('The marketplace is temporarily unavailable. Please try again in a few minutes.'),
            ];
            return LicenseResult::failure($errorMap[$payload['error'] ?? ''] ?? translate('License validation failed.'), $payload['error'] ?? 'api_error');
        }

        $type = (int) ($payload['license_type'] ?? 1);
        $supportedUntil = ! empty($payload['supported_until']) ? Carbon::parse($payload['supported_until']) : null;

        ThemeLicense::updateOrCreate(['theme_slug' => $themeSlug], [
            'purchase_code' => Crypt::encryptString($purchaseCode),
            'license_type' => $type,
            'buyer' => $payload['buyer'] ?? translate('Unknown'),
            'purchased_at' => $payload['purchased_at'] ?? now(),
            'supported_until' => $supportedUntil,
            'domain' => request()->getHost(),
            'verified_at' => now(),
            'status' => 'valid',
            'grace_started_at' => null,
            'signed_payload' => $ls->extractSignedPayload($response->body()),
            'signature' => $data['signature'] ?? null,
        ]);

        Cache::forget(self::CACHE_KEY_PREFIX . $themeSlug);
        Log::info('ThemeLicenseService: theme license verified', ['theme' => $themeSlug, 'type' => $type]);

        return LicenseResult::success([
            'type' => $type,
            'buyer' => $payload['buyer'] ?? translate('Unknown'),
            'purchase_date' => $payload['purchased_at'] ?? now()->toDateString(),
            'license' => $type === 2 ? 'Extended License' : 'Regular License',
        ]);
    }

    /** Cached "is this theme licensed" check (valid or in grace). */
    public function isLicensed(string $themeSlug): bool
    {
        return Cache::remember(self::CACHE_KEY_PREFIX . $themeSlug, self::CACHE_TTL, function () use ($themeSlug) {
            $license = ThemeLicense::where('theme_slug', $themeSlug)->first();
            if (! $license) {
                return false;
            }

            return in_array($license->status, ['valid', 'grace'], true);
        });
    }

    /** License details for the admin Themes page (null if unlicensed). */
    public function getLicenseInfo(string $themeSlug): ?array
    {
        $license = ThemeLicense::where('theme_slug', $themeSlug)->first();
        if (! $license) {
            return null;
        }

        return [
            'license_type' => $license->license_type,
            'license_type_label' => $license->license_type === 2 ? translate('Extended License') : translate('Regular License'),
            'buyer' => $license->buyer,
            'purchase_code_masked' => $this->maskCode($license),
            'purchased_at' => $license->purchased_at?->toDateString(),
            'supported_until' => $license->supported_until?->toDateString(),
            'verified_at' => $license->verified_at?->toDateTimeString(),
            'status' => $license->status,
            'domain_ok' => $license->domain === request()->getHost(),
            'grace_started_at' => $license->grace_started_at?->toDateTimeString(),
        ];
    }

    /** Scheduled/daily re-verification. Mirrors AddonLicenseService::reverify. */
    public function reverify(string $themeSlug): void
    {
        $license = ThemeLicense::where('theme_slug', $themeSlug)->first();
        if (! $license) {
            return;
        }

        try {
            $purchaseCode = Crypt::decryptString($license->purchase_code);
        } catch (\Throwable) {
            $this->markInvalid($themeSlug);
            return;
        }

        // Test-mode licenses never reach the server — keep them valid.
        if (PurchaseCode::matchTestCode($purchaseCode) !== null) {
            $license->update(['verified_at' => now(), 'status' => 'valid', 'grace_started_at' => null]);
            Cache::forget(self::CACHE_KEY_PREFIX . $themeSlug);
            return;
        }

        if (! $this->publicKeyConfigured()) {
            Log::critical('ThemeLicenseService: Re-verify skipped — license server public key not configured.', ['theme' => $themeSlug]);
            return;
        }

        $ls = app(LicenseService::class);
        $version = app(ThemeService::class)->getThemeConfig($themeSlug)['version'] ?? '1.0.0';

        try {
            $response = Http::timeout(15)->retry(2, 1000)->post($ls->verifyEndpoint(), [
                'product' => 'theme',
                'slug' => $themeSlug,
                'purchase_code' => $purchaseCode,
                'domain' => request()?->getHost() ?? settings('site_url', ''),
                'version' => $version,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ThemeLicenseService: Re-verify connection error (transient)', ['theme' => $themeSlug]);
            $this->checkOfflineDeadline($themeSlug, $license);
            return;
        }

        if (! $response->successful()) {
            $this->checkOfflineDeadline($themeSlug, $license);
            return;
        }

        $data = (array) $response->json();
        $payload = $ls->verifySignedResponse($response->body(), $data);
        if ($payload === null) {
            $this->startGracePeriod($themeSlug, $license);
            return;
        }

        if (empty($payload['valid'])) {
            $transientErrors = ['envato_error', 'api_error', 'network', 'connection_error'];
            if (in_array($payload['error'] ?? '', $transientErrors, true)) {
                Log::warning('ThemeLicenseService: Re-verify transient server error — keeping current status', ['theme' => $themeSlug]);
                return;
            }
            $this->startGracePeriod($themeSlug, $license);
            return;
        }

        $license->update([
            'verified_at' => now(),
            'status' => 'valid',
            'grace_started_at' => null,
            'signed_payload' => $ls->extractSignedPayload($response->body()),
            'signature' => $data['signature'] ?? null,
        ]);
        Cache::forget(self::CACHE_KEY_PREFIX . $themeSlug);
    }

    /** Check the License Server for a theme update. Read-only; caches result in settings. */
    public function checkThemeUpdate(string $themeSlug): array
    {
        $fail = static fn (string $error): array => [
            'update_available' => false, 'latest_version' => null, 'changelog' => null, 'error' => $error,
        ];

        $license = ThemeLicense::where('theme_slug', $themeSlug)->first();
        if (! $license) {
            return $fail('not_licensed');
        }

        try {
            $code = Crypt::decryptString($license->purchase_code);
        } catch (\Throwable) {
            return $fail('decrypt_failed');
        }

        $current = app(ThemeService::class)->getThemeConfig($themeSlug)['version'] ?? '0.0.0';

        // Test mode: never contact the server — simulate a manifest so the UI is testable.
        if (PurchaseCode::testModeActive()) {
            $latest = (string) settings("theme_{$themeSlug}_test_latest_version", $this->bumpVersion($current));
            $available = version_compare($latest, $current, '>');
            $changelog = $available ? '(Test mode) Simulated theme update to preview the UI.' : '';

            settings_set("theme_{$themeSlug}_update_available", $available, 'boolean', 'theme');
            settings_set("theme_{$themeSlug}_latest_version", $latest, 'string', 'theme');
            settings_set("theme_{$themeSlug}_update_changelog", $changelog, 'string', 'theme');
            settings_set("theme_{$themeSlug}_update_checked_at", now()->toDateTimeString(), 'string', 'theme');

            return ['update_available' => $available, 'latest_version' => $latest, 'changelog' => $changelog ?: null, 'error' => null];
        }

        $ls = app(LicenseService::class);

        try {
            $response = Http::timeout(20)->retry(2, 1000)->post($ls->updateEndpoint(), [
                'product' => 'theme',
                'slug' => $themeSlug,
                'purchase_code' => $code,
                'domain' => request()?->getHost() ?? settings('site_url', ''),
                'current_version' => $current,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ThemeLicenseService: theme update check network error', ['theme' => $themeSlug]);
            return $fail('network');
        }

        if (! $response->successful()) {
            return $fail('server');
        }

        $payload = $ls->verifySignedResponse($response->body(), (array) $response->json());
        if ($payload === null || empty($payload['valid'])) {
            return $fail($payload['error'] ?? 'unverified');
        }

        $available = (bool) ($payload['update_available'] ?? false);
        settings_set("theme_{$themeSlug}_update_available", $available, 'boolean', 'theme');
        settings_set("theme_{$themeSlug}_latest_version", $payload['latest_version'] ?? '', 'string', 'theme');
        settings_set("theme_{$themeSlug}_update_changelog", $payload['changelog'] ?? '', 'string', 'theme');
        settings_set("theme_{$themeSlug}_update_checked_at", now()->toDateTimeString(), 'string', 'theme');

        return [
            'update_available' => $available,
            'latest_version' => $payload['latest_version'] ?? null,
            'changelog' => $payload['changelog'] ?? null,
            'error' => null,
        ];
    }

    /** Check every installed, licensed theme for updates. @return array<string,array> */
    public function checkAllThemeUpdates(): array
    {
        $results = [];
        foreach (app(ThemeService::class)->getAvailableThemes() as $theme) {
            $slug = $theme['slug'];
            if (! ThemeLicense::where('theme_slug', $slug)->exists()) {
                continue; // no license → not eligible for server updates
            }
            $results[$slug] = $this->checkThemeUpdate($slug);
        }

        return $results;
    }

    /** Remove the stored license (on theme delete). */
    public function revoke(string $themeSlug): void
    {
        ThemeLicense::where('theme_slug', $themeSlug)->delete();
        Cache::forget(self::CACHE_KEY_PREFIX . $themeSlug);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function checkOfflineDeadline(string $themeSlug, ThemeLicense $license): void
    {
        $maxDays = (int) config('license.max_offline_days', 14);
        if ($maxDays <= 0) {
            return;
        }

        $payloadJson = $license->signed_payload;
        $signatureB64 = $license->signature;

        if (blank($payloadJson) || blank($signatureB64)) {
            if ($license->verified_at && abs(now()->diffInDays($license->verified_at)) > $maxDays) {
                Log::warning('ThemeLicenseService: Offline deadline exceeded (no signed proof) — starting grace', ['theme' => $themeSlug]);
                $this->startGracePeriod($themeSlug, $license);
            }
            return;
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return;
        }

        $sig = @base64_decode($signatureB64);
        $pk = @base64_decode($this->getPublicKey());

        if (! $sig || ! $pk || ! @sodium_crypto_sign_verify_detached($sig, $payloadJson, $pk)) {
            Log::warning('ThemeLicenseService: Stored theme proof failed offline signature check — starting grace', ['theme' => $themeSlug]);
            $this->startGracePeriod($themeSlug, $license);
            return;
        }

        $verifiedAt = json_decode($payloadJson, true)['verified_at'] ?? null;
        if (blank($verifiedAt)) {
            return;
        }

        if (abs(now()->diffInDays(Carbon::parse($verifiedAt))) > $maxDays) {
            Log::warning('ThemeLicenseService: Offline deadline exceeded — starting grace', ['theme' => $themeSlug, 'days' => $maxDays]);
            $this->startGracePeriod($themeSlug, $license);
        }
    }

    private function startGracePeriod(string $themeSlug, ThemeLicense $license): void
    {
        $graceHours = 72;

        if ($license->grace_started_at) {
            if (now()->greaterThan($license->grace_started_at->copy()->addHours($graceHours))) {
                $this->markInvalid($themeSlug);
            }
            return;
        }

        $license->update(['status' => 'grace', 'grace_started_at' => now()]);
        Cache::forget(self::CACHE_KEY_PREFIX . $themeSlug);
        Log::warning('ThemeLicenseService: Grace period started', ['theme' => $themeSlug]);
    }

    private function markInvalid(string $themeSlug): void
    {
        ThemeLicense::where('theme_slug', $themeSlug)->update(['status' => 'invalid']);
        Cache::forget(self::CACHE_KEY_PREFIX . $themeSlug);

        // If the invalid theme is the active one, fall back to the default theme.
        if (settings('active_theme') === $themeSlug) {
            settings_set('active_theme', 'default');
            Cache::forget('active_theme_config');
        }

        Log::critical('ThemeLicenseService: Theme license marked invalid', ['theme' => $themeSlug]);
    }

    private function buildTestResult(string $themeSlug, string $purchaseCode, int $type): LicenseResult
    {
        ThemeLicense::updateOrCreate(['theme_slug' => $themeSlug], [
            'purchase_code' => Crypt::encryptString(strtoupper(trim($purchaseCode))),
            'license_type' => $type,
            'buyer' => 'test-buyer',
            'purchased_at' => now(),
            'supported_until' => now()->addYears(10),
            'domain' => request()->getHost(),
            'verified_at' => now(),
            'status' => 'valid',
            'grace_started_at' => null,
        ]);

        Cache::forget(self::CACHE_KEY_PREFIX . $themeSlug);
        Log::info('ThemeLicenseService: theme activated via TEST MODE', ['theme' => $themeSlug, 'type' => $type]);

        return LicenseResult::success([
            'type' => $type,
            'buyer' => 'test-buyer',
            'purchase_date' => now()->toDateString(),
            'license' => $type === 2 ? 'Extended License' : 'Regular License',
        ]);
    }

    private function maskCode(ThemeLicense $license): string
    {
        try {
            $decrypted = Crypt::decryptString($license->purchase_code);
        } catch (\Throwable) {
            return '••••';
        }

        return strlen($decrypted) > 12
            ? substr($decrypted, 0, 8) . '…' . substr($decrypted, -4)
            : '••••';
    }

    private function bumpVersion(string $version): string
    {
        $parts = explode('.', $version ?: '1.0.0');
        $parts[count($parts) - 1] = (string) ((int) end($parts) + 1);

        return implode('.', $parts);
    }

    protected function getPublicKey(): string
    {
        if (app()->runningUnitTests() && app()->has('test.license_public_key')) {
            return app('test.license_public_key');
        }

        return LicenseKey::PUBLIC_KEY;
    }

    private function publicKeyConfigured(): bool
    {
        return $this->getPublicKey() !== LicenseKey::PLACEHOLDER;
    }
}
