<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 cleanup (see settings-refactor-plan.md).
 *
 * `sidebar_config` was written without a group (SidebarController now passes
 * 'appearance'), leaving the only NULL-group row in the settings table. Assign it a
 * group for consistency. It stays a flat row — no `frontend_` prefix, so it is not
 * routed into the appearance blob.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'sidebar_config')
            ->whereNull('group')
            ->update(['group' => 'appearance']);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'sidebar_config')
            ->where('group', 'appearance')
            ->update(['group' => null]);
    }
};
