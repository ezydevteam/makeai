<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the per-plan `plans.currency_code` column. The store now uses ONE base
 * currency (Admin → Settings); the price resolver derives the charge currency from
 * base_currency() (or an explicit per-country price in plan_country_prices), and
 * never read plans.currency_code. Dropping the dead column removes a stale-data
 * red herring. Per-country pricing (plan_country_prices.currency_code) is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plans', 'currency_code')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('currency_code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plans', 'currency_code')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->string('currency_code', 3)->default('USD')->after('price_lifetime');
            });
        }
    }
};
