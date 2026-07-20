<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('last_login_ip');
            $table->string('use_case', 50)->nullable()->after('onboarding_completed_at');
            $table->json('dismissed_tooltips')->nullable()->after('use_case');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed_at', 'use_case', 'dismissed_tooltips']);
        });
    }
};
