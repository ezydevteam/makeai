<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_tools', 'show_header')) {
                $table->boolean('show_header')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('ai_tools', 'show_footer')) {
                $table->boolean('show_footer')->default(true)->after('show_header');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn(['show_header', 'show_footer']);
        });
    }
};
