<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the 'maintenance' category for the going-offline / back-online templates.
 *
 * The baseline migration carries the value too, so a fresh install never runs
 * this. It exists for databases created before the maintenance notices shipped.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        'auth', 'account', 'subscription', 'newsletter', 'custom',
        'affiliate', 'support', 'export', 'content', 'maintenance',
    ];

    public function up(): void
    {
        $this->setCategories(self::CATEGORIES);
    }

    public function down(): void
    {
        DB::table('mail_templates')->where('category', 'maintenance')->delete();

        $this->setCategories(array_values(array_diff(self::CATEGORIES, ['maintenance'])));
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function setCategories(array $categories): void
    {
        // Only MySQL/MariaDB have a real ENUM to widen. SQLite renders enums as a
        // varchar CHECK constraint written at create time, and the baseline
        // migration there already lists 'maintenance'.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $values = implode(', ', array_map(fn (string $c) => "'{$c}'", $categories));

        DB::statement("ALTER TABLE `mail_templates` MODIFY `category` ENUM({$values}) NOT NULL");
    }
};
