<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile the cached users.referral_earnings / referral_count with the commission
 * ledger (approved + paid), now that AffiliateService recomputes them authoritatively
 * instead of incrementing/decrementing. One-time repair of any pre-existing drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_commissions')
            || ! Schema::hasColumn('users', 'referral_earnings')
            || ! Schema::hasColumn('users', 'referral_count')) {
            return;
        }

        $totals = DB::table('affiliate_commissions')
            ->select('referrer_id', DB::raw('SUM(amount) as earnings'), DB::raw('COUNT(*) as cnt'))
            ->whereIn('status', ['approved', 'paid'])
            ->groupBy('referrer_id')
            ->get();

        foreach ($totals as $row) {
            DB::table('users')->where('id', $row->referrer_id)->update([
                'referral_earnings' => round((float) $row->earnings, 4),
                'referral_count' => (int) $row->cnt,
            ]);
        }

        // Zero any user still carrying a stale counter but with no approved/paid rows.
        $earnedIds = $totals->pluck('referrer_id')->all() ?: [0];

        DB::table('users')
            ->whereNotIn('id', $earnedIds)
            ->where(function ($query) {
                $query->where('referral_earnings', '>', 0)->orWhere('referral_count', '>', 0);
            })
            ->update(['referral_earnings' => 0, 'referral_count' => 0]);
    }

    public function down(): void
    {
        // No-op: the reconciled values are the correct ones; nothing to revert to.
    }
};
