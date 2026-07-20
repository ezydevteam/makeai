<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'two_column_submenu') && ! Schema::hasColumn('menu_items', 'mega_menu')) {
                $table->renameColumn('two_column_submenu', 'mega_menu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'mega_menu') && ! Schema::hasColumn('menu_items', 'two_column_submenu')) {
                $table->renameColumn('mega_menu', 'two_column_submenu');
            }
        });
    }
};
