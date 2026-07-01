<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Change the column type from enum to varchar(50)
        DB::statement("ALTER TABLE pages MODIFY COLUMN container_width VARCHAR(50) NOT NULL DEFAULT '1280px'");

        // 2. Migrate legacy values to new width keys
        DB::table('pages')->where('container_width', 'default')->update(['container_width' => '1280px']);
        DB::table('pages')->where('container_width', 'narrow')->update(['container_width' => '1080px']);
        DB::table('pages')->where('container_width', 'wide')->update(['container_width' => '1536px']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert legacy default values
        DB::table('pages')->where('container_width', '1280px')->update(['container_width' => 'default']);
        DB::table('pages')->where('container_width', '1080px')->update(['container_width' => 'narrow']);
        DB::table('pages')->where('container_width', '1536px')->update(['container_width' => 'wide']);

        // Change the column back to enum
        DB::statement("ALTER TABLE pages MODIFY COLUMN container_width ENUM('default', 'wide', 'full', 'narrow') NOT NULL DEFAULT 'default'");
    }
};
