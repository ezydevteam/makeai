<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 4 of the settings refactor (see settings-refactor-plan.md).
 *
 * The social group holds per-platform follow accounts (social_follow_api_key_*,
 * social_follow_external_id_*) plus share/display toggles — all under the `social_`
 * prefix and all read/written via settings(). Rather than a bespoke
 * `social_follow_accounts` table (the blob shim already keeps the encrypted api keys
 * encrypted at rest, and a table rewrite would inadvertently change runtime behavior —
 * see the plan doc's Phase 4 note), collapse the group into a single json blob row,
 * consistent with Phase 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::collapseGroupToBlob('social');
    }

    public function down(): void
    {
        Setting::expandBlobToFlat('social');
    }
};
