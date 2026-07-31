<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];

    /**
     * Cache prefix for settings.
     */
    private const CACHE_PREFIX = 'settings:';

    private const CACHE_TTL = null; // forever, invalidated on write

    /**
     * Cached in place of a missing row. The cache key is derived from the setting key
     * alone, so caching a caller's $default would serve that caller's fallback to every
     * other caller of the same missing key. NUL bytes keep it distinct from any real value.
     */
    private const MISSING = "\0__setting_missing__\0";

    /**
     * Legacy aliases for renamed keys (PART 02 branding normalization).
     * Canonical keys use site_* prefix. app_* is backward-compat.
     */
    /**
     * In-memory, request-scoped setting overrides.
     *
     * Populated by DemoSelection middleware so a demo visitor can preview a theme
     * preset or an addon-owned homepage without persisting anything — demo mode
     * blocks all writes, so the selection can never be saved. Checked before the
     * cache/DB read in getValue(), so it neither pollutes the settings cache nor
     * survives the request. Empty (and thus a complete no-op) in normal operation.
     *
     * @var array<string, mixed>
     */
    protected static array $overrides = [];

    private const ALIASES = [
        'app_name'          => 'site_name',
        'app_url'           => 'site_url',
        'app_logo_light'    => 'site_logo_light',
        'app_logo_dark'     => 'site_logo_dark',
        'app_tagline'       => 'site_tagline',
        'app_description'   => 'site_description',
        'app_favicon_ico'   => 'site_favicon_ico',
        'app_favicon_png'   => 'site_favicon_png',
        'app_og_image'      => 'site_og_image',
        'app_support_email' => 'site_support_email',
        'app_support_url'   => 'site_support_url',
        'app_terms_url'     => 'site_terms_url',
        'app_privacy_url'   => 'site_privacy_url',
    ];

    /**
     * Phase 3 of the settings refactor (see settings-refactor-plan.md).
     *
     * Cohesive config groups that are always read together are collapsed into ONE
     * `json` row per group (key "group:{group}") instead of dozens of flat rows. A key
     * is routed to its group's blob by prefix. The blob stores each subkey as
     * {"v": <rawStoredValue>, "t": <type>} — the raw form, so encrypted values (e.g.
     * notifications' *_app_secret) stay encrypted at rest, exactly like a flat row.
     */
    private const BLOB_GROUP_PREFIXES = [
        'blog_'          => 'blog',
        'gdpr_'          => 'gdpr',
        'notifications_' => 'notifications',
        'broadcasting_'  => 'notifications',
        'rag_'           => 'rag',
        'comments_'      => 'comments',
        'contact_'       => 'contact',
        'maintenance_'   => 'maintenance',
        // One row per scheduled task, created on demand by SystemController — a
        // prefix, not a registry entry, because the key set is open-ended.
        'cron_task_last_run_' => 'system',
        'site_'          => 'branding',
        'frontend_'      => 'appearance',
        'credit_'        => 'billing',
        'license_'       => 'license',
        'storage_'       => 'storage',
        'mail_'          => 'mail',
        'pricing_'       => 'pricing',
        'social_'        => 'social',
        'rl_'            => 'rate_limits',
        // Third-party integrations (AI Settings › External Services). Open-ended like the
        // cron keys: AiManagementController builds `external_{integration}_{provider}_{field}`
        // from config('external-tools.integrations'), so a prefix — not a registry list — is
        // the only thing that can keep up with the catalog.
        'external_'      => 'external_apis',
        // Per-theme config + update bookkeeping: `theme_{slug}_{key}`, written by
        // ThemeService::setSetting/saveThemeSettings and ThemeLicenseService. Also
        // open-ended (one key set per installed theme).
        'theme_'         => 'theme',
        // AdSense/ads toggles from Marketing › Ads. `adsense_publisher_id` misses this
        // prefix by one character, so it is a registry entry below.
        'ads_'           => 'ads',
        // newsletter group: both provider (mailchimp_) and popup/driver (newsletter_) keys.
        // `mailchimp_` does NOT collide with `mail_` — str_starts_with needs the underscore
        // at offset 4 ('mail_'), and 'mailchimp_' has 'c' there.
        'newsletter_'    => 'newsletter',
        'mailchimp_'     => 'newsletter',
    ];

    /**
     * Explicit per-key blob routing for the heterogeneous groups (ai, support, general,
     * features). Unlike the cohesive groups above, these keys share no clean prefix — and
     * some collide across groups (e.g. `ai_reply_suggestion` is `support`, not `ai`), so
     * prefix routing cannot be applied safely. Each key names its group blob explicitly.
     *
     * This registry is consulted BEFORE the prefix table, so it also resolves keys that
     * match a cohesive prefix but belong elsewhere: `blog_enabled`/`notifications_enabled`/
     * `contact_enabled` are feature toggles (group `features`, not blog/notifications/
     * contact), and `site_url` is a `general` key (not branding). This replaces the old
     * BLOB_ROUTING_EXCLUSIONS list — those keys now route to a real blob instead of staying
     * flat. It also folds the prefix-less stragglers in: `default_pricing_country` into the
     * `pricing` blob and `sidebar_config` into the `appearance` blob (neither has the
     * group's prefix). With those, every `settings` row is now a blob row — no flat rows
     * remain.
     */
    private const BLOB_GROUP_KEYS = [
        // ai — model defaults, credit economics.
        // (daily_token_limit/monthly_token_limit/global_daily_budget_usd were dead seeds —
        // 0 readers, purged 2026_07_09, removed from blob by 2026_07_17_000012. Do not re-add;
        // the live budget key is global_daily_ai_budget_usd.)
        'ai_credit_markup'                       => 'ai',
        'ai_credit_min_per_1k'                   => 'ai',
        'default_ai_model'                       => 'ai',
        'default_ai_provider'                    => 'ai',
        'default_credits_new_user'               => 'ai',
        'guest_daily_credit_limit'               => 'ai',
        'max_tokens_per_request'                 => 'ai',
        // Same group, same read pattern as the seven above — these were simply
        // never routed, so they stayed flat while the rest of `ai` collapsed.
        'ai_max_input_chars'                     => 'ai',
        'default_max_tokens'                     => 'ai',
        'fallback_ai_model'                      => 'ai',
        'fallback_ai_provider'                   => 'ai',
        'global_daily_ai_budget_usd'             => 'ai',
        'public_tool_max_output_chars'           => 'ai',
        'user_daily_credit_limit'                => 'ai',
        'user_monthly_credit_limit'              => 'ai',
        // Demo installs only: generation requests allowed per minute per IP. Read by
        // ThrottleAiRequests, which ignores it unless demo.enabled.
        'demo_generation_rate_limit_per_min'     => 'ai',
        'demo_ip_daily_credit_limit'             => 'ai',
        // Bookkeeping written by TokenGuard when it rolls the monthly credit window
        // (group 'ai' at the call site); `credit_` prefix routing misses it — the key is
        // `credits_`, plural.
        'credits_month_last_reset'               => 'ai',

        // support — ticketing/SLA/attachment config
        'ai_reply_suggestion'                    => 'support',
        'allowed_attachment_types'               => 'support',
        'auto_close_resolved_days'               => 'support',
        'max_attachment_size_mb'                 => 'support',
        'max_attachments_per_reply'              => 'support',
        'notify_admin_new_ticket'                => 'support',
        'notify_user_reply'                      => 'support',
        'satisfaction_rating_enabled'            => 'support',
        'sla_first_response_hours'               => 'support',
        'sla_resolution_hours'                   => 'support',

        // general — site-wide runtime/currency/maintenance config
        'active_theme'                           => 'general',
        'app_timezone'                           => 'general',
        'currency_decimals'                      => 'general',
        'currency_position'                      => 'general',
        'currency_symbol'                        => 'general',
        'default_currency'                       => 'general',
        'homepage_template'                      => 'general',
        'maintenance_estimated_restoration_time' => 'general',
        'maintenance_mode'                       => 'general',
        'site_url'                               => 'general',
        // Saved by General Settings alongside the currency keys above (same loop, same
        // `general` group) — it was simply never registered.
        'default_language'                       => 'general',
        // NB: `app_version` is NOT a general key — it belongs to the `system` group below.
        // It was listed here AND there; PHP silently keeps the LAST duplicate, so routing
        // already resolved to `system` while FoundationSeeder seeded it with group=general,
        // which left a permanent stray flat row (no collapse pass matched both). Do not
        // re-add it here.
        // NB: the timezone setting is `app_timezone` (above). A separate `timezone` key was
        // a mis-named dead seed (zero readers) purged by 2026_07_09_000002 but re-seeded by
        // FoundationSeeder; removed from the seed + blob by 2026_07_17_000011. Do not re-add.

        // features — global feature toggles (some share a cohesive prefix; registry wins).
        // subscriptions_enabled is a monetization feature toggle managed on the Features admin
        // screen (FeatureSettingsController writes it group=features); it was mis-registered as
        // `general` and seeded as `license` — realigned here (see 2026_07_17_000013).
        'affiliate_enabled'                      => 'features',
        'blog_enabled'                           => 'features',
        'subscriptions_enabled'                  => 'features',
        'contact_enabled'                        => 'features',
        'email_verification_enabled'             => 'features',
        'notifications_enabled'                  => 'features',
        'registration_enabled'                   => 'features',
        'tickets_enabled'                        => 'features',
        'tools_review_approval_enabled'          => 'features',
        // The rest of the Features admin screen — same writer, same read pattern,
        // they were simply never registered and so stayed flat beside the blob.
        'account_deletion_enabled'               => 'features',
        'byok_enabled'                           => 'features',
        'chains_enabled'                         => 'features',
        'cookie_preferences_enabled'             => 'features',
        'coupons_enabled'                        => 'features',
        'onboarding_enabled'                     => 'features',
        'optin_preferences_enabled'              => 'features',
        'phone_required'                         => 'features',
        'playground_enabled'                     => 'features',
        'tool_embeds_enabled'                    => 'features',
        'two_factor_required'                    => 'features',
        'two_factor_sms_enabled'                 => 'features',

        // mail — provider credentials that miss the `mail_` prefix.
        'sendgrid_api_key'                       => 'mail',
        'ses_key'                                => 'mail',
        'ses_region'                             => 'mail',
        'ses_secret'                             => 'mail',

        // reports
        'export_retention_days'                  => 'reports',

        // system — install/update/scheduler bookkeeping. Written by UpdateService
        // and SystemController, all read together on the admin shell.
        'app_version'                            => 'system',
        'core_update_dismissed_version'          => 'system',
        'core_update_snoozed_until'              => 'system',
        'last_rollback_time'                     => 'system',
        'last_rollback_zip'                      => 'system',
        'last_scheduler_run'                     => 'system',
        'update_available'                       => 'system',
        'update_changelog'                       => 'system',
        'update_last_checked'                    => 'system',
        'update_test_latest_version'             => 'system',
        'update_version'                         => 'system',
        // Heartbeat stamped by AppServiceProvider so the admin shell can flag a dead
        // queue worker; same group arg at the call site.
        'last_queue_worker_run'                  => 'system',

        // NB: no `security` group — its only keys (login_throttle_*, require_email_verification,
        // two_factor_admin) were dead seeds (0 readers), purged 2026_07_09 and removed from the
        // blob by 2026_07_17_000012. The live email-verification toggle is
        // email_verification_enabled (features, above). Do not re-add.

        // pricing — the prefix-less keys in an otherwise pricing_-prefixed blob group.
        // registration_default_plan is saved by PlanController's pricing loop.
        'default_pricing_country'                => 'pricing',
        'registration_default_plan'              => 'pricing',

        // appearance — neither key has the frontend_ prefix but both belong to the group.
        // active_theme_preset is written by ThemePresetService::apply (group 'appearance').
        'sidebar_config'                         => 'appearance',
        'active_theme_preset'                    => 'appearance',

        // ads — the one key that misses the `ads_` prefix (`adsense_`, not `ads_`).
        'adsense_publisher_id'                   => 'ads',
    ];

    /**
     * The blob group a key routes to, or null if the key is stored as a flat row.
     */
    private static function blobGroupFor(string $key): ?string
    {
        // Explicit per-key registry wins over prefix routing (see BLOB_GROUP_KEYS).
        if (isset(self::BLOB_GROUP_KEYS[$key])) {
            return self::BLOB_GROUP_KEYS[$key];
        }

        foreach (self::BLOB_GROUP_PREFIXES as $prefix => $group) {
            if (str_starts_with($key, $prefix)) {
                return $group;
            }
        }

        return null;
    }

    private static function isBlobGroup(string $group): bool
    {
        return in_array($group, self::BLOB_GROUP_PREFIXES, true)
            || in_array($group, self::BLOB_GROUP_KEYS, true);
    }

    private static function blobRowKey(string $group): string
    {
        return 'group:'.$group;
    }

    /**
     * The decoded blob for a group as [subkey => ['v' => raw, 't' => type]], cached.
     */
    private static function loadBlob(string $group): array
    {
        return Cache::rememberForever(self::CACHE_PREFIX.self::blobRowKey($group), function () use ($group) {
            $row = static::where('key', self::blobRowKey($group))->first();
            $decoded = $row && $row->value ? json_decode($row->value, true) : [];

            return is_array($decoded) ? $decoded : [];
        });
    }

    /**
     * Get a setting value by key, with caching.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        // Resolve legacy alias
        $resolvedKey = self::ALIASES[$key] ?? $key;

        // Request-scoped demo override wins over everything and short-circuits before
        // the cache/DB read, so a preview value never leaks into the settings cache.
        if (self::$overrides !== [] && array_key_exists($resolvedKey, self::$overrides)) {
            return self::$overrides[$resolvedKey];
        }

        if ($group = self::blobGroupFor($resolvedKey)) {
            $blob = self::loadBlob($group);
            if (array_key_exists($resolvedKey, $blob)) {
                return self::castStored($blob[$resolvedKey]['v'] ?? null, $blob[$resolvedKey]['t'] ?? 'string');
            }
            // Fall through to any lingering flat row (e.g. freshly seeded, not yet
            // collapsed) before giving up. Blob wins when both exist.
        }

        $cached = Cache::rememberForever(self::CACHE_PREFIX.$resolvedKey, function () use ($resolvedKey) {
            $setting = static::where('key', $resolvedKey)->first();

            return $setting ? $setting->castValue() : self::MISSING;
        });

        return $cached === self::MISSING ? $default : $cached;
    }

    /**
     * Install request-scoped setting overrides (demo preview). Replaces any previously
     * set overrides. These are never written to the DB or the settings cache — they
     * live only for the current request. Pass [] to clear.
     *
     * @param  array<string, mixed>  $overrides  Keyed by canonical setting key.
     */
    public static function overrideForRequest(array $overrides): void
    {
        self::$overrides = $overrides;
    }

    /**
     * Drop any request-scoped overrides. Primarily for test isolation, where a single
     * process serves many requests.
     */
    public static function clearOverrides(): void
    {
        self::$overrides = [];
    }

    /**
     * Whether a key has actually been persisted. Distinct from getValue() returning a
     * non-null result, which cannot tell a stored value apart from a caller's fallback.
     */
    public static function isPersisted(string $key): bool
    {
        $resolvedKey = self::ALIASES[$key] ?? $key;

        if ($group = self::blobGroupFor($resolvedKey)) {
            if (array_key_exists($resolvedKey, self::loadBlob($group))) {
                return true;
            }
            // else fall through — a not-yet-collapsed flat row still counts.
        }

        return static::where('key', $resolvedKey)->exists();
    }

    /**
     * Set a setting value, update cache.
     */
    public static function setValue(string $key, mixed $value, string $type = 'string', ?string $group = null): void
    {
        $storeValue = self::encodeForStore($value, $type);

        if ($blobGroup = self::blobGroupFor($key)) {
            $blob = self::loadBlob($blobGroup);
            $blob[$key] = ['v' => $storeValue, 't' => $type];

            static::updateOrCreate(
                ['key' => self::blobRowKey($blobGroup)],
                ['value' => json_encode($blob), 'type' => 'json', 'group' => $blobGroup],
            );

            // A stale flat row (pre-collapse) would shadow nothing (blob wins on read),
            // but drop it so the table actually shrinks and isPersisted stays honest.
            static::where('key', $key)->delete();

            Cache::forget(self::CACHE_PREFIX.self::blobRowKey($blobGroup));
            Cache::forget(self::CACHE_PREFIX.$key);

            return;
        }

        $attributes = [
            'value' => $storeValue,
            'type' => $type,
        ];

        if ($group !== null) {
            $attributes['group'] = $group;
        }

        static::updateOrCreate(
            ['key' => $key],
            $attributes
        );

        // Invalidate cache
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Encode a value into its stored string form, by type. Shared by flat rows and blobs.
     */
    private static function encodeForStore(mixed $value, string $type): mixed
    {
        if ($type === 'encrypted' && $value !== null) {
            return Crypt::encryptString((string) $value);
        }

        if ($type === 'json' && is_array($value)) {
            return json_encode($value);
        }

        if ($type === 'boolean') {
            return $value ? '1' : '0';
        }

        return $value;
    }

    /**
     * Get settings by group (alias for getByGroup).
     */
    public static function getGroup(string $group): array
    {
        return static::getByGroup($group);
    }

    /**
     * Get settings by group.
     */
    public static function getByGroup(string $group): array
    {
        if (self::isBlobGroup($group)) {
            $result = collect(self::loadBlob($group))
                ->mapWithKeys(fn ($entry, $key) => [$key => self::castStored($entry['v'] ?? null, $entry['t'] ?? 'string')])
                ->toArray();

            // Merge any lingering flat rows (blob wins), for a not-yet-collapsed state.
            foreach (static::where('group', $group)->where('key', 'not like', 'group:%')->get() as $setting) {
                $result += [$setting->key => $setting->castValue()];
            }

            return $result;
        }

        return static::where('group', $group)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->castValue()];
            })
            ->toArray();
    }

    /**
     * Update a group of settings.
     */
    public static function updateGroup(string $group, array $settings): void
    {
        foreach ($settings as $key => $value) {
            // Determine type automatically or assume string
            $type = 'string';
            if (is_bool($value)) {
                $type = 'boolean';
            }
            if (is_array($value)) {
                $type = 'json';
            }
            if (str_contains($key, 'password') || str_contains($key, 'secret') || str_contains($key, 'key')) {
                $type = 'encrypted';
            }

            static::setValue($key, $value, $type, $group);
        }
    }

    /**
     * Collapse every flat row of a group into a single json blob row (key "group:{group}"),
     * then delete the flat rows. Idempotent: a no-op when there are no flat rows, and an
     * existing blob value always wins over a flat row (so a re-seeded default never
     * clobbers a value the operator already changed).
     *
     * Shared by the Phase 3 migration and the foundation seeder so both installs and
     * fresh setups end up blobbed identically.
     */
    public static function collapseGroupToBlob(string $group): void
    {
        if (! self::isBlobGroup($group)) {
            return;
        }

        // Only absorb rows whose key actually routes to this blob. A group can hold a
        // key that is deliberately not prefix-routed (e.g. pricing.default_pricing_country,
        // which has no `pricing_` prefix); that one must stay a flat row or reads — which
        // go through the flat path — would miss it after collapse.
        $flatRows = static::where('group', $group)
            ->where('key', 'not like', 'group:%')
            ->get(['key', 'value', 'type'])
            ->filter(fn ($row) => self::blobGroupFor($row->key) === $group);

        if ($flatRows->isEmpty()) {
            return;
        }

        $blob = self::loadBlob($group);

        foreach ($flatRows as $row) {
            if (! array_key_exists($row->key, $blob)) {
                $blob[$row->key] = ['v' => $row->value, 't' => $row->type ?: 'string'];
            }
        }

        static::updateOrCreate(
            ['key' => self::blobRowKey($group)],
            ['value' => json_encode($blob), 'type' => 'json', 'group' => $group],
        );

        $keys = $flatRows->pluck('key')->all();
        static::whereIn('key', $keys)->delete();

        Cache::forget(self::CACHE_PREFIX.self::blobRowKey($group));
        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }

    /**
     * Fold EVERY flat row that routes to a blob into that blob, whatever its `group`
     * column says. Returns the number of rows absorbed.
     *
     * collapseGroupToBlob() is keyed on the group COLUMN, so it can only ever absorb a
     * row whose column already matches its routed group. Rows written with a mismatched
     * group — `app_version` seeded as `general` but registered `system`, `tickets_enabled`
     * seeded as `support` but registered `features` — were invisible to every collapse
     * pass and stayed flat forever. This sweep routes by KEY, so no mismatch can hide a
     * row from it.
     *
     * Same semantics as collapseGroupToBlob otherwise: idempotent, and an existing blob
     * value always wins over the flat row (a re-seeded default never clobbers an operator
     * edit). Keys that route nowhere are left alone as flat rows.
     */
    public static function foldFlatRowsIntoBlobs(): int
    {
        $rows = static::where('key', 'not like', 'group:%')->get(['key', 'value', 'type']);

        $byGroup = [];
        foreach ($rows as $row) {
            if ($group = self::blobGroupFor($row->key)) {
                $byGroup[$group][] = $row;
            }
        }

        $absorbed = 0;

        foreach ($byGroup as $group => $groupRows) {
            $blob = self::loadBlob($group);

            foreach ($groupRows as $row) {
                if (! array_key_exists($row->key, $blob)) {
                    $blob[$row->key] = ['v' => $row->value, 't' => $row->type ?: 'string'];
                }
            }

            static::updateOrCreate(
                ['key' => self::blobRowKey($group)],
                ['value' => json_encode($blob), 'type' => 'json', 'group' => $group],
            );

            $keys = array_map(fn ($row) => $row->key, $groupRows);
            $absorbed += static::whereIn('key', $keys)->delete();

            Cache::forget(self::CACHE_PREFIX.self::blobRowKey($group));
            foreach ($keys as $key) {
                Cache::forget(self::CACHE_PREFIX.$key);
            }
        }

        return $absorbed;
    }

    /**
     * Reverse of collapseGroupToBlob: write each blob subkey back as a flat row and
     * drop the blob row. Used by the Phase 3 migration's down() path.
     */
    public static function expandBlobToFlat(string $group): void
    {
        if (! self::isBlobGroup($group)) {
            return;
        }

        $blob = self::loadBlob($group);

        foreach ($blob as $key => $entry) {
            static::updateOrCreate(
                ['key' => $key],
                ['value' => $entry['v'] ?? null, 'type' => $entry['t'] ?? 'string', 'group' => $group],
            );
        }

        static::where('key', self::blobRowKey($group))->delete();

        Cache::forget(self::CACHE_PREFIX.self::blobRowKey($group));
        foreach (array_keys($blob) as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }

    /**
     * Cast the stored value to its proper PHP type.
     */
    public function castValue(): mixed
    {
        return self::castStored($this->value, $this->type);
    }

    /**
     * Cast a stored (string) value to its PHP type. Static twin of castValue() so blob
     * entries can be cast without hydrating a model.
     */
    private static function castStored(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            'encrypted' => self::tryDecrypt($value),
            default => $value,
        };
    }

    /**
     * Decrypt a stored ciphertext, tolerating a corrupt or empty payload (returns null)
     * rather than throwing. Some legacy rows hold an empty encrypted value
     * (e.g. license_purchase_code); a bulk read via getByGroup() must not blow up on one
     * bad row.
     */
    private static function tryDecrypt(string $value): mixed
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }
    }

    /**
     * Flush all settings cache.
     */
    public static function flushCache(): void
    {
        $settings = static::pluck('key');
        foreach ($settings as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }
}
