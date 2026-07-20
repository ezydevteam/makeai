<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['mega_menu', 'mega_menu_content'],
                fn (string $column): bool => Schema::hasColumn('menu_items', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'mega_menu')) {
                $table->boolean('mega_menu')->default(false)->after('requires_auth');
            }
            if (! Schema::hasColumn('menu_items', 'mega_menu_content')) {
                $table->longText('mega_menu_content')->nullable()->after('mega_menu');
            }
        });
    }
};
