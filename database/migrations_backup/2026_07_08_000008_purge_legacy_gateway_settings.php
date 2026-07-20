<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purge legacy duplicated Stripe credentials from the global settings table.
 *
 * Gateway credentials live (encrypted) in payment_gateways.credentials and are read
 * at runtime via PaymentGateway::getCredential() — including the Cashier config
 * bootstrap in AppServiceProvider. An old syncLegacySettings() also mirrored the
 * Stripe keys into `settings` (stripe_secret_key / stripe_publishable_key /
 * stripe_webhook_secret), but nothing ever read them back — a redundant second copy
 * of secrets. That sync is removed; this deletes any rows it already wrote so secrets
 * live in exactly one place (the gateway record).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->whereIn('key', ['stripe_secret_key', 'stripe_publishable_key', 'stripe_webhook_secret'])
            ->delete();
    }

    public function down(): void
    {
        // No-op: these were redundant duplicates; the source of truth
        // (payment_gateways.credentials) is untouched.
    }
};
