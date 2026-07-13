<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop verified-dead role/user/admin columns (audit: no runtime readers).
 *
 *  users.personal_api_keys       — dead JSON duplicate; live BYOK is the user_byok
 *                                  (formerly user_api_keys) table read by PromptBuilder
 *  admin_permissions.description — never seeded with content, never read/displayed
 *  admins.must_change_password   — set false by installer/seeder, never enforced/read
 */
return new class extends Migration
{
    private array $map = [
        'users' => ['personal_api_keys'],
        'admin_permissions' => ['description'],
        'admins' => ['must_change_password'],
    ];

    public function up(): void
    {
        foreach ($this->map as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $present = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
            if ($present === []) {
                continue;
            }
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'personal_api_keys')) {
            Schema::table('users', fn (Blueprint $t) => $t->json('personal_api_keys')->nullable());
        }
        if (Schema::hasTable('admin_permissions') && ! Schema::hasColumn('admin_permissions', 'description')) {
            Schema::table('admin_permissions', fn (Blueprint $t) => $t->string('description')->nullable());
        }
        if (Schema::hasTable('admins') && ! Schema::hasColumn('admins', 'must_change_password')) {
            Schema::table('admins', fn (Blueprint $t) => $t->boolean('must_change_password')->default(false));
        }
    }
};
