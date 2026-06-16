<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->index(['status', 'aggregated_at', 'created_at'], 'ai_usage_logs_status_aggregated_created_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['email_marketing', 'last_login_at'], 'users_email_marketing_last_login_index');
            $table->index(['subscription_status', 'is_active'], 'users_subscription_status_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropIndex('ai_usage_logs_status_aggregated_created_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_marketing_last_login_index');
            $table->dropIndex('users_subscription_status_is_active_index');
        });
    }
};
