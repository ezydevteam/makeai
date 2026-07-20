<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Display-only rename of the `settings.manage` permission.
 *
 * "Manage All Settings" undersold what the tick actually does. It is the broadest
 * non-super-admin grant in the app: it opens every settings page (storage, integrations,
 * extensions, OAuth, social counters, GDPR, notifications) AND the system tools (health,
 * updates, maintenance mode, cache, rate limits), plus advertisements, menus/appearance
 * and credit settings — because every route that was re-gated onto a granular slug still
 * accepts `settings.manage` as the legacy fallback.
 *
 * Only `admin_permissions.name` changes. The slug, the route gates and every grant are
 * untouched, so no role gains or loses access.
 */
return new class extends Migration
{
    private const SLUG = 'settings.manage';

    private const NEW_NAME = 'Full Settings & System Access';

    private const OLD_NAME = 'Manage All Settings';

    public function up(): void
    {
        $this->rename(self::NEW_NAME);
    }

    public function down(): void
    {
        $this->rename(self::OLD_NAME);
    }

    private function rename(string $name): void
    {
        if (! Schema::hasTable('admin_permissions')) {
            return;
        }

        DB::table('admin_permissions')
            ->where('slug', self::SLUG)
            ->update(['name' => $name, 'updated_at' => now()]);
    }
};
