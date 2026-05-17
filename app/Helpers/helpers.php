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
use App\Services\ThemeService;
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
     * @param  string  $text  The text to translate (used as key)
     * @param  array  $replace  Placeholder replacements
     *
     * @example translate('Welcome back, :name', ['name' => 'John'])
     */
    function translate(string $text, array $replace = []): string
    {
        // For now, get from Laravel's trans system
        // Will be extended to use DB translations when Translation system is built
        $translated = $text;

        // Try database translations if available
        try {
            $locale = app()->getLocale();
            $cacheKey = "translations:{$locale}";

            $translations = Cache::remember($cacheKey, 86400, function () use ($locale) {
                $language = Language::where('code', $locale)->where('is_active', true)->first();
                if (! $language) {
                    return [];
                }

                return Translation::where('language_id', $language->id)
                    ->pluck('value', 'key')
                    ->toArray();
            });

            if (isset($translations[$text])) {
                $translated = $translations[$text];
            }
        } catch (Exception $e) {
            // DB not ready yet, use original text
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

if (! function_exists('format_currency')) {
    /**
     * Format a number as currency using the active currency.
     *
     * @example format_currency(29.99) → '$29.99'
     */
    function format_currency(float $amount, ?string $currency = null): string
    {
        try {
            if ($currency) {
                $curr = Currency::where('code', $currency)->first();
            } else {
                $curr = Currency::getDefault();
            }

            if (! $curr) {
                return '$'.number_format($amount, 2);
            }

            return $curr->symbol.number_format($amount, $curr->decimal_places);
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
            $fromRate = Currency::where('code', $from)->value('exchange_rate') ?? 1;
            $toRate = Currency::where('code', $to)->value('exchange_rate') ?? 1;

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
        if (! $user || $user->credits < $amount) {
            return false;
        }

        $user->deductCredits($amount, $reason);
        Cache::forget("user_credits:{$userId}");

        return true;
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

if (! function_exists('get_license_type')) {
    /**
     * Get the license type: 1 = Regular, 2 = Extended.
     */
    function get_license_type(): int
    {
        return (int) settings('license_type', 1);
    }
}

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

if (! function_exists('isProAvailable')) {
    /**
     * Check if subscription/billing features are available.
     * Requires Extended License AND subscriptions enabled in settings.
     */
    function isProAvailable(): bool
    {
        return is_extended_license() && (bool) settings('subscriptions_enabled', false);
    }
}

if (! function_exists('license_verified')) {
    /**
     * Quick check if license is verified (from cache).
     */
    function license_verified(): bool
    {
        return Cache::remember('license_verified', 604800, function () {
            return ! empty(settings('license_key'));
        });
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
