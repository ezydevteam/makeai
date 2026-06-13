<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('type')->nullable();
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedBigInteger('total_input_tokens')->default(0);
            $table->unsignedBigInteger('total_output_tokens')->default(0);
            $table->decimal('total_cost_usd', 12, 6)->default(0);
            $table->decimal('total_credits', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['date', 'provider', 'model', 'type'], 'analytics_daily_unique');
        });

        if (! Schema::hasColumn('ai_usage_logs', 'aggregated_at')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                $table->timestamp('aggregated_at')->nullable()->after('response_time_ms');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
        Schema::dropColumns('ai_usage_logs', ['aggregated_at']);
    }
};
