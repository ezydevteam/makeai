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
        // Unique constraint is already created in the original table creation migration,
        // so we do nothing here to avoid duplicate index errors.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do nothing
    }
};
