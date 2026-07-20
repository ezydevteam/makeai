<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Migrations\Migration;

/**
 * Remove the dead `timezone` setting — a mis-named duplicate of `app_timezone` (the key the
 * app actually reads, via display_tz()). It had zero readers and was purged by
 * 2026_07_09_000002, but FoundationSeeder re-seeded it, and Phase 6 then folded it into the
 * `general` blob. This strips it from the blob (and any stray flat row) and stops it being
 * re-created (seed line + BLOB_GROUP_KEYS entry removed in the same change).
 *
 * Irreversible by design (down() is a no-op), matching 2026_07_09_000002: the row carried no
 * data worth restoring — `app_timezone` is and always was the live value.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = Setting::where('key', 'group:general')->first();
        if ($row) {
            $blob = json_decode($row->value ?? '[]', true) ?: [];
            if (array_key_exists('timezone', $blob)) {
                unset($blob['timezone']);
                $row->update(['value' => json_encode($blob)]);
            }
        }

        // Any stray flat row (not-yet-collapsed installs).
        Setting::where('key', 'timezone')->delete();

        Cache::forget('settings:group:general');
        Cache::forget('settings:timezone');
    }

    public function down(): void
    {
        // Intentionally irreversible — dead orphan key, no consumers; app_timezone is the
        // live value.
    }
};
