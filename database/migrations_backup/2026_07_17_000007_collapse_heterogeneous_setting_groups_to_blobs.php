<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 6 of the settings refactor (see settings-refactor-plan.md).
 *
 * Collapses the last four heterogeneous groups — ai, support, general, features — into
 * one json blob row each. These have no clean shared prefix (and `ai_` collides between
 * the `ai` and `support` groups), so unlike the Phase 3 groups they route via the explicit
 * Setting::BLOB_GROUP_KEYS registry rather than a prefix.
 *
 * collapseGroupToBlob only absorbs keys that actually route to the group (blob-wins,
 * idempotent), so operator-changed values survive and any stray non-registered key in
 * one of these groups stays a flat row.
 *
 * Note: the former BLOB_ROUTING_EXCLUSIONS keys (blog_enabled / notifications_enabled /
 * contact_enabled → features, site_url → general) are now registry entries, so this
 * migration folds them into their real blob instead of leaving them flat.
 *
 * Deliberately NOT blobbed: security (login_throttle_*, two_factor_*) and the rate-limit
 * abuse scalars stay flat.
 */
return new class extends Migration
{
    private array $groups = ['ai', 'support', 'general', 'features'];

    public function up(): void
    {
        foreach ($this->groups as $group) {
            Setting::collapseGroupToBlob($group);
        }
    }

    public function down(): void
    {
        foreach ($this->groups as $group) {
            Setting::expandBlobToFlat($group);
        }
    }
};
