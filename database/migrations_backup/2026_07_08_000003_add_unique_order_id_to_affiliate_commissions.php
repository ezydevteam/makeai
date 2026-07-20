<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One commission per order. createCommissionForPayment() guards with a non-locking
 * exists() check, which two concurrent activations (a webhook racing an admin
 * approval) could both pass — double-commissioning the referrer. A UNIQUE index on
 * order_id makes the guarantee atomic. NULL order_ids (manual/adjustment rows) may
 * coexist since SQL unique indexes don't collide on NULL.
 *
 * Idempotent: the index may already exist on installs where an earlier equivalent
 * migration ran, so adding it is wrapped and a duplicate is ignored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_commissions')) {
            return;
        }

        // Collapse any pre-existing duplicates (keep the earliest per order).
        $dupes = DB::table('affiliate_commissions')
            ->select('order_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('order_id')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            DB::table('affiliate_commissions')
                ->where('order_id', $dupe->order_id)
                ->where('id', '!=', $dupe->keep_id)
                ->delete();
        }

        try {
            Schema::table('affiliate_commissions', function (Blueprint $table) {
                $table->unique('order_id', 'affiliate_commissions_order_id_unique');
            });
        } catch (\Throwable $e) {
            // Index already present (a prior equivalent migration applied it) — safe.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliate_commissions')) {
            return;
        }

        try {
            Schema::table('affiliate_commissions', function (Blueprint $table) {
                $table->dropUnique('affiliate_commissions_order_id_unique');
            });
        } catch (\Throwable $e) {
            // Not present — nothing to drop.
        }
    }
};
