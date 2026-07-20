<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the `demo_revenue` settings group — 24 keys (demo_revenue_YYYY_MM and
 * demo_signups_YYYY_MM, 12 months each). These were monthly time-series values
 * seeded by DemoSeeder but read by NO code path: the admin dashboard computes
 * every revenue/signup figure directly from the `payments` and `users` base
 * tables (Payment::where('status','completed')->sum('amount'), User counts).
 *
 * The data belongs to those base tables, which already hold it, so these KV rows
 * were dead duplicated demo data. DemoSeeder no longer writes them.
 *
 * Irreversible by design (down() is a no-op): the rows carry no data worth
 * restoring, and demo charts are driven by the seeded payments/users rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('group', 'demo_revenue')->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible — dead orphan demo keys with no consumers.
    }
};
