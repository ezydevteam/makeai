<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->boolean('show_regenerate')->default(true)->after('max_variants');
            $table->boolean('show_improve')->default(true)->after('show_regenerate');
            $table->boolean('show_editor')->default(true)->after('show_improve');
        });
    }

    public function down(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn(['show_regenerate', 'show_improve', 'show_editor']);
        });
    }
};
