<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 8 of the settings refactor (see settings-refactor-plan.md) — the last flat row.
 *
 * Folds `sidebar_config` (group `appearance`, no `frontend_` prefix) into the existing
 * `appearance` blob via the BLOB_GROUP_KEYS registry. After this the `settings` table has
 * ZERO flat rows — every row is a group blob.
 *
 * Idempotent re-collapse (blob-wins, so existing frontend_* values are kept). The down()
 * pulls ONLY sidebar_config back to a flat row, leaving the frontend_* keys blobbed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::collapseGroupToBlob('appearance');
    }

    public function down(): void
    {
        $row = Setting::where('key', 'group:appearance')->first();
        if ($row) {
            $blob = json_decode($row->value ?? '[]', true) ?: [];
            if (isset($blob['sidebar_config'])) {
                $entry = $blob['sidebar_config'];
                Setting::updateOrCreate(
                    ['key' => 'sidebar_config'],
                    ['value' => $entry['v'] ?? null, 'type' => $entry['t'] ?? 'json', 'group' => 'appearance'],
                );
                unset($blob['sidebar_config']);
                $row->update(['value' => json_encode($blob)]);
            }
        }

        Setting::flushCache();
    }
};
