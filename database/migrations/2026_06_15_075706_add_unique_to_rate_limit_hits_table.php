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
        Schema::table('rate_limit_hits', function (Blueprint $table) {
            $table->unique(['key', 'window_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rate_limit_hits', function (Blueprint $table) {
            $table->dropUnique(['rate_limit_hits_key_window_start_unique']);
        });
    }
};
