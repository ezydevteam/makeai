<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'currency_code')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->string('currency_code', 3)->default('USD')->after('price_lifetime');
            });
        }

        Schema::create('plan_country_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_yearly', 12, 2)->nullable();
            $table->decimal('price_lifetime', 12, 2)->nullable();
            $table->unsignedInteger('trial_monthly_days')->nullable();
            $table->unsignedInteger('trial_yearly_days')->nullable();
            $table->unsignedInteger('trial_lifetime_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'country_code']);
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_country_prices');

        if (Schema::hasColumn('plans', 'currency_code')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('currency_code');
            });
        }
    }
};
