<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cosmetic reindex of the settings table.
 *
 * The blob refactor deleted the old flat rows, so the surviving blob rows carry
 * sparse, high primary keys (77–227) and the auto-increment counter sits well
 * past them. Nothing references settings.id — every read is by `key` — so the
 * ids are free to renumber. This rewrites them 1..N in a deliberate group order
 * (general and the core feature/system toggles first) and resets the counter.
 *
 * down() is intentionally a no-op: the previous ids were an artefact of deletion
 * order, not information worth restoring.
 */
return new class extends Migration
{
    /**
     * Group blobs in the order they should be numbered. Any group not listed
     * (e.g. one added by a later feature) is appended alphabetically after these,
     * so a missing entry degrades gracefully rather than dropping the row.
     */
    private const GROUP_PRIORITY = [
        'general',
        'features',
        'system',
        'branding',
        'appearance',
        'mail',
        'notifications',
        'ai',
        'billing',
        'pricing',
        'license',
        'social',
        'newsletter',
        'comments',
        'contact',
        'maintenance',
        'support',
        'gdpr',
        'rate_limits',
        'reports',
    ];

    public function up(): void
    {
        $rows = DB::table('settings')->get();

        if ($rows->isEmpty()) {
            return;
        }

        $priority = array_flip(self::GROUP_PRIORITY);
        $fallback = count($priority);

        // Sort by a single composite key rather than an array of closures — the
        // latter form did not order reliably here. Known groups sort by their
        // priority index; anything unlisted falls after them by group name, and
        // key breaks ties so the result is deterministic on re-run.
        $ordered = $rows->sortBy(function ($r) use ($priority, $fallback) {
            $rank = $priority[$r->group] ?? $fallback;

            return sprintf('%04d|%s|%s', $rank, $r->group ?? '', $r->key);
        })->values();

        DB::transaction(function () use ($ordered) {
            DB::table('settings')->delete();

            $id = 1;
            foreach ($ordered as $row) {
                $data = (array) $row;
                $data['id'] = $id++;
                DB::table('settings')->insert($data);
            }
        });

        $this->resetAutoIncrement($ordered->count() + 1);
    }

    public function down(): void
    {
        // No-op — the prior ids held no meaning.
    }

    private function resetAutoIncrement(int $next): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `settings` AUTO_INCREMENT = {$next}");
        }
        // SQLite tracks the counter from the max rowid inserted above, so the
        // next auto id is already N+1 — nothing to reset.
    }
};
