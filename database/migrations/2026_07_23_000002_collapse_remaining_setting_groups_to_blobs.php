<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Finishes the settings blob refactor for three groups the earlier phases left
 * behind as flat rows.
 *
 * - contact: already prefix-routed to `contact`, but its seven rows were never
 *   collapsed, so every read fell through the blob path to the flat rows.
 * - maintenance: had no routing at all until this release.
 * - ai: seven keys collapsed in phase 3; eight more never got a mapping and
 *   stayed flat next to the blob that owns the same group.
 *
 * Note on blog_enabled: it lives in `group:features`, not `group:blog`, even
 * though the `blog_` prefix would suggest otherwise. BLOB_GROUP_KEYS routes it to
 * `features` explicitly and the explicit registry wins over prefix routing — it
 * is a feature toggle that happens to start with `blog_`. Leave it where it is.
 */
return new class extends Migration
{
    private array $groups = ['contact', 'maintenance', 'ai'];

    public function up(): void
    {
        // collapseGroupToBlob() forgets the cache for the rows it absorbs.
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
