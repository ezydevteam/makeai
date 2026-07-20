<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Display-only regrouping on the Roles & Permissions screen.
 *
 * `admin_permissions.group` is presentational — it is only ever used to groupBy for the
 * UI, and nothing gates on it (permission checks read `slug`). Moving "AI Settings" next
 * to the other AI permissions, and "Payment Settings" next to the payment ones, is
 * therefore a pure UI change: no slug, no route gate, and no grant is touched, so every
 * role keeps exactly the access it had.
 */
return new class extends Migration
{
    /** @var array<string, string> permission slug => new (display) group */
    private const REGROUPED = [
        'settings.ai' => 'ai',
        'settings.payment' => 'payments',
    ];

    /** @var array<string, string> the original groups, for down() */
    private const ORIGINAL = [
        'settings.ai' => 'settings',
        'settings.payment' => 'settings',
    ];

    public function up(): void
    {
        $this->apply(self::REGROUPED);
    }

    public function down(): void
    {
        $this->apply(self::ORIGINAL);
    }

    /**
     * @param  array<string, string>  $map
     */
    private function apply(array $map): void
    {
        if (! Schema::hasTable('admin_permissions')) {
            return;
        }

        foreach ($map as $slug => $group) {
            DB::table('admin_permissions')
                ->where('slug', $slug)
                ->update(['group' => $group, 'updated_at' => now()]);
        }
    }
};
