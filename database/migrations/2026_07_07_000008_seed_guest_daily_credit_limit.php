<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Regular-license credit quotas: a guest per-IP daily credit allowance for public
 * tools (0 = no guest cap). Seeds a sensible default so a free (Regular-license)
 * tool site meters anonymous usage out of the box; Extended admins can set 0 to
 * disable. The logged-in daily/monthly allowances reuse the existing
 * user_daily_credit_limit / user_monthly_credit_limit settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        settings_set('guest_daily_credit_limit', '20', 'integer', 'ai');
    }

    public function down(): void
    {
        //
    }
};
