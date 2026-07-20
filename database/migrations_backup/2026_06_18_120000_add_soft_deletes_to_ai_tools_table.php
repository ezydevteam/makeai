<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ai_tools', 'deleted_at')) {
            Schema::table('ai_tools', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_tools', 'deleted_at')) {
            Schema::table('ai_tools', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
