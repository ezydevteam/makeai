<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('access_level', 30)->default('inherit')->after('requires_login');
        });

        // Migrate existing boolean flags to access_level
        DB::table('categories')
            ->where('requires_pro', true)
            ->update(['access_level' => 'premium']);

        DB::table('categories')
            ->where('requires_pro', false)
            ->where('requires_login', true)
            ->update(['access_level' => 'login']);

        DB::table('categories')
            ->where('requires_pro', false)
            ->where('requires_login', false)
            ->update(['access_level' => 'guest']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('access_level');
        });
    }
};
