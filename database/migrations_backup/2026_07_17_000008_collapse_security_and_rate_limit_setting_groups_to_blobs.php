<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 7 of the settings refactor (see settings-refactor-plan.md) — the final sweep.
 *
 * Blobs the last two flat groups and folds in the last non-prefixed straggler:
 *  - `security` (login_throttle_*, require_email_verification, two_factor_admin) → registry blob.
 *  - `rate_limits` abuse scalars (rl_ai_abuse_*) → new `rl_` prefix blob. (The rate-limit tier
 *    MATRIX already lives in the dedicated `rate_limit_rules` table since Phase 1; only these
 *    non-matrix scalars remained in `settings`.)
 *  - `default_pricing_country` → folded into the existing `pricing` blob (it has no `pricing_`
 *    prefix, so the BLOB_GROUP_KEYS registry now routes it there).
 *
 * After this only `sidebar_config` (group `appearance`, no `frontend_` prefix) remains flat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::collapseGroupToBlob('security');
        Setting::collapseGroupToBlob('rate_limits');
        // pricing blob already exists; this idempotent re-collapse just absorbs the
        // default_pricing_country straggler (blob-wins, so existing pricing_* values are kept).
        Setting::collapseGroupToBlob('pricing');
    }

    public function down(): void
    {
        Setting::expandBlobToFlat('security');
        Setting::expandBlobToFlat('rate_limits');

        // Pricing: pull ONLY the default_pricing_country straggler back to a flat row — the
        // pricing_* keys predate this migration and must stay blobbed.
        $row = Setting::where('key', 'group:pricing')->first();
        if ($row) {
            $blob = json_decode($row->value ?? '[]', true) ?: [];
            if (isset($blob['default_pricing_country'])) {
                $entry = $blob['default_pricing_country'];
                Setting::updateOrCreate(
                    ['key' => 'default_pricing_country'],
                    ['value' => $entry['v'] ?? null, 'type' => $entry['t'] ?? 'string', 'group' => 'pricing'],
                );
                unset($blob['default_pricing_country']);
                $row->update(['value' => json_encode($blob)]);
            }
        }

        Setting::flushCache();
    }
};
