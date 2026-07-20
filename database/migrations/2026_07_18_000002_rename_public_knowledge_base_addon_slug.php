<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The Knowledge Base addon directory was renamed public-knowledge-base -> ai-knowledge-base.
 *
 * AddonService derives an addon's slug from its directory name, so without this the scanner
 * would register a brand-new (inactive) `ai-knowledge-base` row, deactivate the old one, and
 * orphan every stored setting and the license record. Renaming the slug in place keeps the
 * addon active with its configuration and license intact.
 */
return new class extends Migration
{
    private const OLD = 'public-knowledge-base';

    private const NEW = 'ai-knowledge-base';

    public function up(): void
    {
        $this->rename(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->rename(self::NEW, self::OLD);
    }

    private function rename(string $from, string $to): void
    {
        foreach ([['addons', 'slug'], ['addon_settings', 'addon_slug'], ['addon_licenses', 'addon_slug']] as [$table, $column]) {
            // Nothing to migrate: either this already ran, or the addon was never installed.
            // Bailing out per-table matters — clearing the target rows unconditionally would
            // wipe the renamed data on a second run.
            if (! DB::table($table)->where($column, $from)->exists()) {
                continue;
            }

            // A row under the target slug can only exist here if the addon scanner ran against
            // the renamed directory before this migration did. That is a freshly-scanned stub
            // with no settings, so it is dropped in favour of the real row being renamed.
            DB::table($table)->where($column, $to)->delete();
            DB::table($table)->where($column, $from)->update([$column => $to]);
        }

        // AddonSetting caches one blob per slug forever; both keys must go.
        Cache::forget('addon_settings:'.$from);
        Cache::forget('addon_settings:'.$to);
        Cache::forget('active_addons_list');
    }
};
