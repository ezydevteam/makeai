<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Migrations\Migration;

/**
 * Relocate `subscriptions_enabled` into the `features` blob — its true group. It's a
 * monetization feature toggle managed on the Features admin screen
 * (FeatureSettingsController writes group=features), but was mis-registered as `general`
 * (Phase 6) and seeded as `license` (a direct-insert flat row that no collapse absorbed).
 * Depending on install history it currently sits in the general blob (written via the old
 * registry) or as a stray flat row (fresh-seeded, group=license).
 *
 * This moves whichever exists into the features blob, preserving the operator's value, then
 * removes the old copy. Idempotent + blob-wins (an existing features value is never clobbered).
 */
return new class extends Migration
{
    public function up(): void
    {
        $entry = null;

        // 1. Pull it out of the general blob (old registry target), if present.
        $general = Setting::where('key', 'group:general')->first();
        if ($general) {
            $blob = json_decode($general->value ?? '[]', true) ?: [];
            if (array_key_exists('subscriptions_enabled', $blob)) {
                $entry = $blob['subscriptions_enabled'];
                unset($blob['subscriptions_enabled']);
                $general->update(['value' => json_encode($blob)]);
            }
        }

        // 2. Or a stray flat row (fresh-seeded installs seeded it as group=license).
        $flat = Setting::where('key', 'subscriptions_enabled')->first();
        if ($flat) {
            $entry ??= ['v' => $flat->value, 't' => $flat->type ?: 'boolean'];
            $flat->delete();
        }

        // 3. Write into the features blob, without clobbering an existing value.
        if ($entry !== null) {
            $features = Setting::where('key', 'group:features')->first();
            $fblob = $features ? (json_decode($features->value ?? '[]', true) ?: []) : [];
            if (! array_key_exists('subscriptions_enabled', $fblob)) {
                $fblob['subscriptions_enabled'] = $entry;
                Setting::updateOrCreate(
                    ['key' => 'group:features'],
                    ['value' => json_encode($fblob), 'type' => 'json', 'group' => 'features'],
                );
            }
        }

        Cache::forget('settings:group:general');
        Cache::forget('settings:group:features');
        Cache::forget('settings:subscriptions_enabled');
    }

    public function down(): void
    {
        // Best-effort reverse: move it back into the general blob (its pre-migration home on
        // most installs). Value preserved.
        $features = Setting::where('key', 'group:features')->first();
        if (! $features) {
            return;
        }
        $fblob = json_decode($features->value ?? '[]', true) ?: [];
        if (! array_key_exists('subscriptions_enabled', $fblob)) {
            return;
        }
        $entry = $fblob['subscriptions_enabled'];
        unset($fblob['subscriptions_enabled']);
        $features->update(['value' => json_encode($fblob)]);

        $general = Setting::where('key', 'group:general')->first();
        $gblob = $general ? (json_decode($general->value ?? '[]', true) ?: []) : [];
        $gblob['subscriptions_enabled'] = $entry;
        Setting::updateOrCreate(
            ['key' => 'group:general'],
            ['value' => json_encode($gblob), 'type' => 'json', 'group' => 'general'],
        );

        Cache::forget('settings:group:general');
        Cache::forget('settings:group:features');
        Cache::forget('settings:subscriptions_enabled');
    }
};
