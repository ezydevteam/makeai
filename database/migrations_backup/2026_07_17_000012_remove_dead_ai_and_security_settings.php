<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Migrations\Migration;

/**
 * Remove the remaining dead re-seeded keys the 2026_07_09_000002 audit purged but
 * FoundationSeeder kept re-creating (and Phases 6–7 then folded into blobs). All verified
 * 0-readers:
 *   - ai blob:       daily_token_limit, monthly_token_limit, global_daily_budget_usd
 *                    (live budget key is global_daily_ai_budget_usd)
 *   - security blob: login_throttle_max, login_throttle_minutes, require_email_verification,
 *                    two_factor_admin — the ENTIRE group, so the group:security blob row is
 *                    dropped outright (live email toggle is email_verification_enabled)
 *
 * Companion change removed the seed lines + BLOB_GROUP_KEYS entries so they can't resurrect.
 * Irreversible by design (down() no-op), matching 2026_07_09_000002 — dead orphans, no data
 * worth restoring.
 */
return new class extends Migration
{
    private array $deadByGroup = [
        'ai' => ['daily_token_limit', 'monthly_token_limit', 'global_daily_budget_usd'],
        'security' => ['login_throttle_max', 'login_throttle_minutes', 'require_email_verification', 'two_factor_admin'],
    ];

    public function up(): void
    {
        foreach ($this->deadByGroup as $group => $keys) {
            $row = Setting::where('key', 'group:'.$group)->first();
            if ($row) {
                $blob = json_decode($row->value ?? '[]', true) ?: [];
                foreach ($keys as $k) {
                    unset($blob[$k]);
                }
                // Drop the blob row entirely if nothing remains (security is all-dead).
                if ($blob === []) {
                    $row->delete();
                } else {
                    $row->update(['value' => json_encode($blob)]);
                }
            }

            // Any stray flat rows (not-yet-collapsed installs).
            Setting::whereIn('key', $keys)->delete();

            Cache::forget('settings:group:'.$group);
            foreach ($keys as $k) {
                Cache::forget('settings:'.$k);
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible — dead orphan keys, no consumers.
    }
};
