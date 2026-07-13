<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `crypto_payments` tool integration was removed from the external-tools
 * catalog (it duplicated the real CoinGate payment gateway). Any keys an admin
 * saved for it are now orphaned `external_crypto_payments_*` rows — delete them.
 * A no-op on installs that never configured it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'like', 'external_crypto_payments_%')
            ->delete();
    }

    public function down(): void
    {
        // Irreversible: the integration no longer exists, so there is nothing to restore.
    }
};
