<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend the mail_templates.category ENUM to include 'affiliate' so the affiliate
 * notification templates (commission/payout) can be seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mail_templates MODIFY category ENUM('auth','account','subscription','newsletter','custom','affiliate') NOT NULL");
        }
        // SQLite stores enums as plain text (no CHECK) — nothing to change.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mail_templates MODIFY category ENUM('auth','account','subscription','newsletter','custom') NOT NULL");
        }
    }
};
