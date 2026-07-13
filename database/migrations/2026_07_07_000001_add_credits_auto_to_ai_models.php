<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ai_models', 'credits_auto')) {
            Schema::table('ai_models', function (Blueprint $table) {
                $table->boolean('credits_auto')->default(true)->after('credits_per_1k');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_models', 'credits_auto')) {
            Schema::table('ai_models', function (Blueprint $table) {
                $table->dropColumn('credits_auto');
            });
        }
    }
};
