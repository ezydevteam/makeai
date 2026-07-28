<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How much of a user's wallet was bought separately, rather than granted by their plan.
 *
 * Plan credits are an allowance: each renewal tops the wallet back up to the plan figure
 * so it cannot compound. Top-ups are a purchase, and were being absorbed by exactly that
 * mechanism — a subscriber holding 15,000 (10,000 plan + a 5,000 top-up) received nothing
 * at renewal, spent down, and over two or three cycles the credits they had paid for
 * quietly disappeared into the allowance.
 *
 * With this column the renewal target becomes `plan credits + topup_credits`, so a
 * top-up survives every renewal that follows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('topup_credits', 12, 4)->default(0)->after('credits');
        });

        // Backfill from the ledger so existing top-up buyers are not written off by this
        // change. Capped at the current balance, since anything already spent is gone —
        // the same invariant deductCredits() maintains from here on.
        //
        // Done in PHP rather than one UPDATE: capping needs a two-argument minimum, which
        // is LEAST() on MySQL and MIN() on sqlite, and the test suite runs on sqlite. Only
        // users who have actually bought a top-up are touched, so this is a handful of
        // writes rather than a table scan.
        $totals = DB::table('credit_transactions')
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->where('type', 'topup')
            ->where('amount', '>', 0)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        foreach ($totals as $userId => $total) {
            $balance = (float) DB::table('users')->where('id', $userId)->value('credits');
            $protected = min((float) $total, $balance);

            if ($protected > 0) {
                DB::table('users')->where('id', $userId)->update(['topup_credits' => $protected]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('topup_credits');
        });
    }
};
