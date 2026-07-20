<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('badge_text', 50)->nullable()->after('icon');
            $table->string('badge_color', 20)->nullable()->after('badge_text');
            $table->boolean('is_active')->default(true)->after('badge_color');
            $table->string('requires_auth', 20)->default('none')->after('is_active');
            $table->boolean('mega_menu')->default(false)->after('requires_auth');
            $table->longText('mega_menu_content')->nullable()->after('mega_menu');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn([
                'badge_text',
                'badge_color',
                'is_active',
                'requires_auth',
                'mega_menu',
                'mega_menu_content',
            ]);
        });
    }
};
