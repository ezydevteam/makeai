<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ads')->whereNull('title')->update(['title' => 'Advertisement']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ads MODIFY type ENUM('adsense','custom_html','image_link') NOT NULL DEFAULT 'image_link'");
        }

        Schema::table('ads', function (Blueprint $table) {
            foreach (['name', 'placement', 'content', 'image_path', 'starts_at', 'ends_at', 'views'] as $column) {
                if (Schema::hasColumn('ads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (! Schema::hasColumn('ads', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('ads', 'placement')) {
                $table->string('placement')->nullable();
            }
            if (! Schema::hasColumn('ads', 'content')) {
                $table->longText('content')->nullable();
            }
            if (! Schema::hasColumn('ads', 'image_path')) {
                $table->string('image_path')->nullable();
            }
            if (! Schema::hasColumn('ads', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
            if (! Schema::hasColumn('ads', 'ends_at')) {
                $table->timestamp('ends_at')->nullable();
            }
            if (! Schema::hasColumn('ads', 'views')) {
                $table->unsignedBigInteger('views')->default(0);
            }
        });
    }
};
