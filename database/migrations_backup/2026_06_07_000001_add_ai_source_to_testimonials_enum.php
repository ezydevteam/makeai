<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE `testimonials` CHANGE `source` `source` ENUM('manual','google','trustpilot','ai') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE `testimonials` CHANGE `source` `source` ENUM('manual','google','trustpilot') NOT NULL DEFAULT 'manual'");
    }
};
