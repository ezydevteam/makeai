<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove seeded settings keys that no code path ever reads (verified in the
 * settings key-space audit). Three groups:
 *
 *  1. Mis-named dead seeds — a differently-named key is the one the code reads:
 *       timezone                  (live: app_timezone)
 *       global_daily_budget_usd   (live: global_daily_ai_budget_usd)
 *       require_email_verification(live: email_verification_enabled)
 *  2. Dead limit seeds — the plan columns they mirrored were already dropped:
 *       daily_token_limit, monthly_token_limit
 *  3. Never-read misc seeds:
 *       login_throttle_max, login_throttle_minutes, two_factor_admin
 *  4. The template_* config cluster (24 keys across 6 groups) — seeded by the
 *     2026_06_12 template migrations but read nowhere in PHP or the frontend.
 *
 * Irreversible by design (down() is a no-op): these rows carry no data worth
 * restoring, and the seed values live in their original migrations if ever needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereIn('key', [
            'timezone',
            'global_daily_budget_usd',
            'require_email_verification',
            'daily_token_limit',
            'monthly_token_limit',
            'login_throttle_max',
            'login_throttle_minutes',
            'two_factor_admin',
        ])->delete();

        DB::table('settings')->whereIn('group', [
            'template_social',
            'template_marketing',
            'template_content',
            'template_ecom',
            'template_developer',
            'template_academic',
        ])->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible — these were dead orphan keys with no
        // consumers; their original seed values remain in the 2026_06_12 and
        // FoundationSeeder sources if a re-seed is ever needed.
    }
};
