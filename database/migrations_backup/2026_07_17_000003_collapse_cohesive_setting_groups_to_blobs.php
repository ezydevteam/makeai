<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 3 of the settings refactor (see settings-refactor-plan.md).
 *
 * Collapses cohesive, always-read-together config groups from dozens of flat rows each
 * into a single json blob row per group ("group:{group}"). Routing and blob encoding
 * live in the Setting model; this migration just triggers the collapse for the first
 * batch of groups. Reversible into flat rows via down().
 *
 * blog_enabled / notifications_enabled are NOT affected — they live in the `features`
 * group and stay as flat rows (see Setting::BLOB_ROUTING_EXCLUSIONS).
 */
return new class extends Migration
{
    private array $groups = ['blog', 'gdpr', 'notifications'];

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
