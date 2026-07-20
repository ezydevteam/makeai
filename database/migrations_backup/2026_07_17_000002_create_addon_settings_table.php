<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the settings refactor (see settings-refactor-plan.md).
 *
 * Moves per-addon config out of the shared key/value `settings` table (where it was
 * ~42% of all rows) into a slug-scoped `addon_settings` table. Backfills from the
 * existing `addon_{slug}_{key}` rows — copying value+type verbatim so casting is
 * unchanged — then deletes them.
 *
 * Addon slugs use hyphens and never underscores, so the first underscore after the
 * `addon_` prefix cleanly separates slug from key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_settings', function (Blueprint $table) {
            $table->id();
            $table->string('addon_slug', 100);
            $table->string('key', 191);
            $table->longText('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();

            $table->unique(['addon_slug', 'key']);
            $table->index('addon_slug');
        });

        $this->backfillFromSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_settings');
    }

    private function backfillFromSettings(): void
    {
        // Scope to the 'addon' group: every per-addon setting is written there
        // (addon_setting_set / AddonService both pass 'addon'), while global keys that
        // merely start with "addon_" (e.g. addon_license_recheck_days) are in other
        // groups and must stay in settings. parseKey() is a second, structural filter.
        $rows = DB::table('settings')
            ->where('group', 'addon')
            ->where('key', 'like', 'addon_%')
            ->get(['key', 'value', 'type']);

        $now = now();
        $movedKeys = [];
        $insert = [];

        foreach ($rows as $row) {
            $parsed = $this->parseKey($row->key);
            if ($parsed === null) {
                continue; // not a slug-scoped addon key — leave it in settings
            }
            [$slug, $key] = $parsed;

            $insert[] = [
                'addon_slug' => $slug,
                'key' => $key,
                'value' => $row->value,
                'type' => $row->type ?: 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $movedKeys[] = $row->key;
        }

        foreach (array_chunk($insert, 200) as $chunk) {
            DB::table('addon_settings')->insert($chunk);
        }

        if ($movedKeys !== []) {
            DB::table('settings')->whereIn('key', $movedKeys)->delete();
            foreach ($movedKeys as $key) {
                Cache::forget('settings:'.$key);
            }
            Cache::forget('settings:all');
        }
    }

    /**
     * Split "addon_{slug}_{key}" into [slug, key] at the first underscore after the
     * prefix. Slugs contain no underscores, so this is unambiguous.
     *
     * @return array{0:string,1:string}|null
     */
    private function parseKey(string $key): ?array
    {
        if (! str_starts_with($key, 'addon_')) {
            return null;
        }
        $body = substr($key, 6); // strip "addon_"

        $sep = strpos($body, '_');
        if ($sep === false || $sep === 0) {
            return null; // no key part, or empty slug
        }

        $slug = substr($body, 0, $sep);
        $settingKey = substr($body, $sep + 1);

        return $settingKey === '' ? null : [$slug, $settingKey];
    }
};
