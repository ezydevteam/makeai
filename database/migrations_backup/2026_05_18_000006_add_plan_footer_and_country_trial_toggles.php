<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('bottom_info_text')->nullable()->after('description');
        });

        Schema::table('plan_country_prices', function (Blueprint $table) {
            $table->boolean('trial_monthly_enabled')->default(false)->after('vat_percentage');
            $table->boolean('trial_yearly_enabled')->default(false)->after('trial_monthly_enabled');
            $table->boolean('trial_lifetime_enabled')->default(false)->after('trial_yearly_enabled');
        });

        DB::table('plan_country_prices')->where('price_monthly', 0)->update(['trial_monthly_enabled' => true]);
        DB::table('plan_country_prices')->where('price_yearly', 0)->update(['trial_yearly_enabled' => true]);
        DB::table('plan_country_prices')->where('price_lifetime', 0)->update(['trial_lifetime_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('plan_country_prices', function (Blueprint $table) {
            $table->dropColumn([
                'trial_monthly_enabled',
                'trial_yearly_enabled',
                'trial_lifetime_enabled',
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('bottom_info_text');
        });
    }
};
