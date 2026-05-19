<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('original_price_monthly', 12, 2)->nullable()->after('currency_code');
            $table->decimal('original_price_yearly', 12, 2)->nullable()->after('original_price_monthly');
            $table->decimal('original_price_lifetime', 12, 2)->nullable()->after('original_price_yearly');
            $table->decimal('vat_percentage', 5, 2)->default(0)->after('original_price_lifetime');
            $table->boolean('trial_all_countries')->default(false)->after('vat_percentage');
        });

        Schema::table('plan_country_prices', function (Blueprint $table) {
            $table->decimal('original_price_monthly', 12, 2)->nullable()->after('currency_code');
            $table->decimal('original_price_yearly', 12, 2)->nullable()->after('original_price_monthly');
            $table->decimal('original_price_lifetime', 12, 2)->nullable()->after('original_price_yearly');
            $table->decimal('vat_percentage', 5, 2)->nullable()->after('price_lifetime');
        });
    }

    public function down(): void
    {
        Schema::table('plan_country_prices', function (Blueprint $table) {
            $table->dropColumn([
                'original_price_monthly',
                'original_price_yearly',
                'original_price_lifetime',
                'vat_percentage',
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'original_price_monthly',
                'original_price_yearly',
                'original_price_lifetime',
                'vat_percentage',
                'trial_all_countries',
            ]);
        });
    }
};
