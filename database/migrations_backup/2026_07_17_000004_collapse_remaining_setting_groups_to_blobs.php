<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 3 (second batch) of the settings refactor (see settings-refactor-plan.md).
 *
 * Collapses the remaining prefix-clean cohesive groups into one json blob row each,
 * reusing Setting::collapseGroupToBlob (which only absorbs keys that actually route to
 * the blob, so e.g. pricing.default_pricing_country stays a flat row).
 *
 * Deliberately NOT blobbed: ai, support, general, security, features — their keys have
 * no shared prefix (or prefixes that collide across groups), so they stay flat.
 */
return new class extends Migration
{
    private array $groups = [
        'pricing', 'branding', 'appearance', 'rag', 'billing',
        'license', 'storage', 'mail', 'comments', 'contact',
    ];

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
