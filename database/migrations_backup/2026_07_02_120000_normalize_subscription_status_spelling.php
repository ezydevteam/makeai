<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The codebase mixed 'canceled' and 'cancelled'. The canonical spelling is
     * 'cancelled' (matching GatewaySubscription::cancel() and the admin UI).
     */
    public function up(): void
    {
        DB::table('users')
            ->where('subscription_status', 'canceled')
            ->update(['subscription_status' => 'cancelled']);

        DB::table('billing_subscriptions')
            ->where('status', 'canceled')
            ->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        // One-way normalization; nothing to restore.
    }
};
