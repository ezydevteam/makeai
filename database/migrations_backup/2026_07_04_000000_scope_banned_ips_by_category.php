<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope IP bans by endpoint category so an IP can hold independent bans per
 * scope (mirroring user_rate_limit_overrides). Replaces the single-column
 * ip_address unique with a composite (ip_address, category) unique, and gives
 * 'category' a non-null 'all' default. 'all'/'site' act as global scopes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill any legacy NULL scopes to a global ban before tightening.
        DB::table('banned_ips')->whereNull('category')->update(['category' => 'all']);

        Schema::table('banned_ips', function (Blueprint $table) {
            $table->dropUnique(['ip_address']);
        });

        Schema::table('banned_ips', function (Blueprint $table) {
            $table->string('category')->default('all')->nullable(false)->change();
        });

        Schema::table('banned_ips', function (Blueprint $table) {
            $table->unique(['ip_address', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('banned_ips', function (Blueprint $table) {
            $table->dropUnique(['ip_address', 'category']);
        });

        Schema::table('banned_ips', function (Blueprint $table) {
            $table->string('category')->nullable()->default(null)->change();
        });

        Schema::table('banned_ips', function (Blueprint $table) {
            $table->unique(['ip_address']);
        });
    }
};
