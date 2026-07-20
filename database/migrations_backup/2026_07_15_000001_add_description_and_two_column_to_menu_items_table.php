<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'description')) {
                $table->string('description', 255)->nullable()->after('label');
            }
            if (! Schema::hasColumn('menu_items', 'two_column_submenu')) {
                $table->boolean('two_column_submenu')->default(false)->after('mega_menu_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['description', 'two_column_submenu'],
                fn (string $column): bool => Schema::hasColumn('menu_items', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
