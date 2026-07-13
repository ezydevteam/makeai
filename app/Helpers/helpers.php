<?php

/**
 * MakeAI — Core Helper Functions
 *
 * Auto-loaded via composer.json. Available globally.
 */

use App\Models\Currency;
use App\Models\Language;
use App\Models\Setting;
use App\Models\Translation;
use App\Models\User;
use App\Services\AddonService;
use App\Services\ThemeSettingsService;
use App\Services\ThemeService;
use App\Services\TranslationService;
use App\Support\CountryCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Settings Helpers
|--------------------------------------------------------------------------
*/

if (! function_exists('settings')) {
    /**
     * Get a setting value by key.
     *
     *
     * @example settings('site_name')           → 'MakeAI'
     * @example settings('openai_key')          → decrypted value
     * @example settings('max_tokens', 2000)    → fallback default
     */
    function settings(string $key, mixed $default = null): mixed
    {
        try {
            if (str_starts_with($key, 'global_tools_') || $key === 'show_tool_credit_costs') {
                $themeSettings = app(\App\Services\ThemeSettingsService::class)->getResolvedFrontendToolPage();
                if (array_key_exists($key, $themeSettings)) {
                    return $themeSettings[$key];
                }
            }

            $value = Setting::getValue($key, $default);

            return $value ?? $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (! function_exists('settings_set')) {
    /**
     * Set a setting value.
     *
     * @param  string  $type  string|boolean|integer|json|encrypted
     */
    function settings_set(string $key, mixed $value, string $type = 'string', ?string $group = null): void
    {
        if (str_starts_with($key, 'global_tools_') || $key === 'show_tool_credit_costs') {
            $themeSettingsService = app(\App\Services\ThemeSettingsService::class);
            $themeSettings = $themeSettingsService->getStoredToolPageSettings();
            $themeSettings[$key] = $value;
            $themeSettingsService->saveToolPageSettings($themeSettings);
            return;
        }

        Setting::setValue($key, $value, $type, $group);
    }
}

if (! function_exists('admin_setting')) {
    /**
     * Alias for settings() — used in admin context for clarity.
     */
    function admin_setting(string $key, mixed $default = null): mixed
    {
        return settings($key, $default);
    }
}

/*
|--------------------------------------------------------------------------
| Translation Helper
|--------------------------------------------------------------------------
*/

if (! function_exists('translate')) {
    /**
     * Translate a string using the active language.
     * Falls back to the $text itself if no translation found.
     *
     * Supports pluralization with pipe syntax:
     *   translate('One item|:count items', ['count' => 5])   → "5 items"
     *   translate('No items|One item|:count items', ['count' => 0]) → "No items"
     *
     * @param  string  $text  The text to translate (used as key)
     * @param  array  $replace  Placeholder replacements
     *
     * @example translate('Welcome back, :name', ['name' => 'John'])
     */
    function translate(string $text, array $replace = []): string
    {
        $translated = $text;

        try {
            $locale = app()->getLocale();
            $translations = TranslationService::getForLocale($locale);

            if (isset($translations[$text])) {
                $translated = $translations[$text];
            }
        } catch (Exception $e) {
            // DB not ready yet, use original text
        }

        // Pluralization: split on | based on count
        if (array_key_exists('count', $replace)) {
            $count = (int) $replace['count'];
            $parts = explode('|', $translated);
            if (count($parts) === 2) {
                $translated = $count === 1 ? $parts[0] : $parts[1];
            } elseif (count($parts) === 3) {
                $translated = $count === 0 ? $parts[0] : ($count === 1 ? $parts[1] : $parts[2]);
            }
        }

        // Apply replacements
        foreach ($replace as $key => $value) {
            $translated = str_replace(":{$key}", $value, $translated);
        }

        return $translated;
    }
}

/*
|--------------------------------------------------------------------------
| Currency Helpers
|--------------------------------------------------------------------------
*/

if (! function_exists('base_currency')) {
    /**
     * The store's single base currency code — the ONE source of truth.
     *
     * Set in Admin → Settings (General) as `default_currency`. Everything the admin
     * enters (plans, top-ups, credit price, coupons, commissions) is denominated in
     * this currency; provider AI cost is USD and converted into it. Falls back to the
     * legacy `pricing_currency_code`, then USD, so it is always safe to call.
     */
    function base_currency(): string
    {
        $code = settings('default_currency') ?: settings('pricing_currency_code') ?: 'USD';

        return strtoupper((string) $code);
    }
}

if (! function_exists('format_currency')) {
    /**
     * Format a number as currency using the store base currency (or an explicit one).
     *
     * @example format_currency(29.99) → '$29.99'
     */
    function format_currency(float $amount, ?string $currency = null): string
    {
        try {
            $code = $currency ?: (Currency::getDefault()?->code ?: base_currency());

            return CountryCatalog::formatMoney($amount, $code);
        } catch (Exception $e) {
            return '$'.number_format($amount, 2);
        }
    }
}

if (! function_exists('convert_currency')) {
    /**
     * Convert amount between currencies using stored exchange rates.
     */
    function convert_currency(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        try {
            $fromRate = (float) (Currency::where('code', $from)->value('exchange_rate') ?? 1);
            $toRate = (float) (Currency::where('code', $to)->value('exchange_rate') ?? 1);

            // A zero/negative rate (bad tick, unsynced currency) would divide by zero —
            // PHP returns INF/NAN with only a warning, not an exception, so the catch
            // never fires and callers would render garbage. Fall back to the input.
            if ($fromRate <= 0 || $toRate <= 0) {
                return $amount;
            }

            // Convert: from → USD → to
            $usdAmount = $amount / $fromRate;

            return round($usdAmount * $toRate, 6);
        } catch (Exception $e) {
            return $amount;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Credit Helpers
|--------------------------------------------------------------------------
*/

if (! function_exists('get_user_credits')) {
    /**
     * Get user's current credit balance.
     */
    function get_user_credits(?int $userId = null): float
    {
        $userId = $userId ?? Auth::id();
        if (! $userId) {
            return 0;
        }

        return Cache::remember("user_credits:{$userId}", 60, function () use ($userId) {
            return (float) User::where('id', $userId)->value('credits') ?? 0;
        });
    }
}

if (! function_exists('deduct_credits')) {
    /**
     * Deduct credits from a user + log transaction.
     */
    function deduct_credits(int $userId, float $amount, string $reason): bool
    {
        $user = User::find($userId);
        if (! $user) {
            return false;
        }

        // Mode-correct (used by addons too): in metered mode this enforces the wallet
        // balance and drains it (returns false when short); in quota mode (Regular
        // license) it meters the resetting allowance and never fails on balance.
        $charged = $user->chargeCredits($amount, $reason);
        Cache::forget("user_credits:{$userId}");

        return $charged;
    }
}

if (! function_exists('add_credits')) {
    /**
     * Add credits to a user + log transaction.
     */
    function add_credits(int $userId, float $amount, string $reason, ?int $fromUserId = null): bool
    {
        $user = User::find($userId);
        if (! $user) {
            return false;
        }

        $type = $fromUserId ? 'referral' : 'bonus';
        $user->addCredits($amount, $type, $reason, $fromUserId ? ['from_user' => $fromUserId] : []);
        Cache::forget("user_credits:{$userId}");

        return true;
    }
}

if (! function_exists('estimate_token_cost')) {
    /**
     * Estimate the credit cost for a given number of tokens on a model.
     */
    function estimate_token_cost(int $tokens, string $model): float
    {
        $costPer1k = (float) settings("ai_cost_{$model}", 0.002);

        return ($tokens / 1000) * $costPer1k;
    }
}

/*
|--------------------------------------------------------------------------
| License Helpers (Part 1.2)
|--------------------------------------------------------------------------
*/

if (! function_exists('is_extended_license')) {
    /**
     * Check if the license is Extended (type 2).
     */
    function is_extended_license(): bool
    {
        return get_license_type() === 2;
    }
}

if (! function_exists('is_regular_license')) {
    /**
     * Check if the license is Regular (type 1).
     */
    function is_regular_license(): bool
    {
        return get_license_type() === 1;
    }
}

if (! function_exists('get_license_buyer')) {
    /**
     * Get the buyer username from Envato license.
     */
    function get_license_buyer(): string
    {
        return settings('license_buyer', '');
    }
}

/*
|--------------------------------------------------------------------------
| Misc Helpers
|--------------------------------------------------------------------------
*/

if (! function_exists('active_theme')) {
    /**
     * Get the slug of the currently active theme.
     */
    function active_theme(): string
    {
        return settings('active_theme', 'default');
    }
}

if (! function_exists('theme_setting')) {
    /**
     * Get a theme setting value for the active theme.
     */
    function theme_setting(string $key, mixed $default = null): mixed
    {
        $presetService = app(ThemeSettingsService::class);

        if (in_array($key, ['custom_css', 'custom_header_code', 'custom_footer_code'], true)) {
            $customCode = $presetService->getStoredCustomCodeSettings();

            return $customCode[$key] ?? $default;
        }

        $themeSettings = $presetService->getResolvedFrontendTheme();
        if (array_key_exists($key, $themeSettings)) {
            return $themeSettings[$key];
        }

        return app(ThemeService::class)->getSetting($key, $default);
    }
}

if (! function_exists('is_addon_active')) {
    /**
     * Check if a specific addon is active.
     */
    function is_addon_active(string $slug): bool
    {
        return app(AddonService::class)->isActive($slug);
    }
}

if (! function_exists('app_version')) {
    /**
     * Get the current app version.
     */
    function app_version(): string
    {
        return settings('app_version', '1.0.0');
    }
}

if (! function_exists('is_maintenance')) {
    /**
     * Check if the app is in maintenance mode.
     */
    function is_maintenance(): bool
    {
        return (bool) settings('maintenance_mode', false);
    }
}

if (! function_exists('addon_setting')) {
    /**
     * Read an addon setting.
     */
    function addon_setting(string $addonSlug, string $key, mixed $default = null): mixed
    {
        return settings("addon_{$addonSlug}_{$key}", $default);
    }
}

if (! function_exists('addon_setting_set')) {
    /**
     * Set an addon setting.
     */
    function addon_setting_set(string $addonSlug, string $key, mixed $value, string $type = 'string'): void
    {
        settings_set("addon_{$addonSlug}_{$key}", $value, $type, 'addon');
    }
}

if (! function_exists('media_url')) {
    /**
     * Resolve a stored media path to a browser-usable URL, correct for the active
     * storage driver (local server or any S3-compatible bucket selected in
     * Settings → Storage).
     *
     * - Absolute URLs and data URIs are returned unchanged.
     * - A legacy stored value that already includes the `/storage/` prefix is
     *   normalised so it is not doubled up.
     * - Local driver: returns a root-relative `/storage/...` URL. This is deliberate —
     *   it avoids mixed-content breakage when APP_URL has drifted from the real host
     *   (a very common shared-hosting misconfiguration) and matches the historical
     *   behaviour of the app.
     * - Cloud driver: returns the disk's fully-qualified URL (bucket/CDN/custom domain).
     *
     * Returns '' for a null/blank path so it is safe to interpolate into `src`/`href`.
     */
    function media_url(?string $path): string
    {
        if (blank($path)) {
            return '';
        }

        $path = ltrim($path);

        if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        // Normalise a legacy value that already carries the public prefix.
        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        if (config('filesystems.disks.public.driver', 'local') === 'local') {
            return '/storage/'.$relative;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($relative);
    }
}

if (! function_exists('media_path')) {
    /**
     * Inverse of {@see media_url()}: reduce a stored value (which may be a relative
     * key, a `/storage/...` root-relative URL, or a fully-qualified disk/CDN URL) back
     * to the bare disk key used by Storage::disk('public'). Used by delete paths and
     * the one-time normalisation migration so the database holds relative keys only.
     *
     * Returns '' for blank input. An off-disk absolute URL (not under the public disk)
     * is returned unchanged since it has no local key.
     */
    function media_path(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        $value = trim($value);

        // Strip a fully-qualified public-disk / CDN origin if present.
        $base = rtrim(\Illuminate\Support\Facades\Storage::disk('public')->url(''), '/');
        if ($base !== '' && str_starts_with($value, $base)) {
            $value = ltrim(substr($value, strlen($base)), '/');
        }

        // Untouched absolute URL that isn't ours — nothing to reduce.
        if (preg_match('#^(https?:)?//#i', $value)) {
            return $value;
        }

        $value = ltrim($value, '/');
        if (str_starts_with($value, 'storage/')) {
            $value = substr($value, strlen('storage/'));
        }

        return $value;
    }
}

if (! function_exists('store_public_upload')) {
    /**
     * Store an uploaded file on the public disk, failing loudly if the write does not
     * succeed. Returns the stored RELATIVE key (never a URL).
     *
     * The public disk runs with `throw => false` (so a transient cloud error degrades
     * rather than 500-ing the site), which means store()/storeAs() return `false` on
     * failure. Callers that assign that result blindly would persist a broken path and
     * flash "saved". This helper turns a false return into a {@see \App\Exceptions\StorageWriteException}.
     *
     * When $replacing is given, the previous file is deleted ONLY AFTER the new write
     * succeeds — so a failed upload never destroys the existing file (no data loss).
     *
     * @param  \Illuminate\Http\UploadedFile|\Symfony\Component\HttpFoundation\File\UploadedFile  $file
     */
    function store_public_upload($file, string $directory, ?string $replacing = null, ?string $name = null): string
    {
        $path = $name !== null
            ? $file->storeAs($directory, $name, 'public')
            : $file->store($directory, 'public');

        if ($path === false || $path === '' || $path === null) {
            throw \App\Exceptions\StorageWriteException::forUpload();
        }

        if ($replacing !== null) {
            $oldKey = media_path($replacing);
            if ($oldKey !== '' && $oldKey !== $path
                && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldKey)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldKey);
            }
        }

        return $path;
    }
}

if (! function_exists('addon_manifest')) {
    /**
     * Get an addon's manifest array.
     */
    function addon_manifest(string $slug): array
    {
        $addon = \App\Models\Addon::where('slug', $slug)->first();
        return $addon ? $addon->manifest ?? [] : [];
    }
}

if (! function_exists('addon_version')) {
    /**
     * Get an addon's version string.
     */
    function addon_version(string $slug): string
    {
        $addon = \App\Models\Addon::where('slug', $slug)->first();
        return $addon ? $addon->version ?? '0.0.0' : '0.0.0';
    }
}
