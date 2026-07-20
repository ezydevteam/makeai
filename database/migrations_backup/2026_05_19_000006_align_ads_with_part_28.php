<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ads MODIFY type ENUM('adsense','custom_html','image_link','image','script') NOT NULL DEFAULT 'image_link'");
        }

        Schema::table('ads', function (Blueprint $table) {
            if (! Schema::hasColumn('ads', 'zone')) {
                $table->string('zone', 100)->nullable()->after('placement');
            }
            if (! Schema::hasColumn('ads', 'title')) {
                $table->string('title')->nullable()->after('zone');
            }
            if (! Schema::hasColumn('ads', 'adsense_client')) {
                $table->string('adsense_client', 100)->nullable()->after('title');
            }
            if (! Schema::hasColumn('ads', 'adsense_slot')) {
                $table->string('adsense_slot', 50)->nullable()->after('adsense_client');
            }
            if (! Schema::hasColumn('ads', 'adsense_format')) {
                $table->string('adsense_format', 50)->nullable()->after('adsense_slot');
            }
            if (! Schema::hasColumn('ads', 'custom_html')) {
                $table->longText('custom_html')->nullable()->after('adsense_format');
            }
            if (! Schema::hasColumn('ads', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('image_path');
            }
            if (! Schema::hasColumn('ads', 'link_target')) {
                $table->string('link_target', 20)->default('_blank')->after('link_url');
            }
            if (! Schema::hasColumn('ads', 'show_to')) {
                $table->string('show_to', 30)->default('all')->after('link_target');
            }
            if (! Schema::hasColumn('ads', 'start_at')) {
                $table->timestamp('start_at')->nullable()->after('show_to');
            }
            if (! Schema::hasColumn('ads', 'end_at')) {
                $table->timestamp('end_at')->nullable()->after('start_at');
            }
            if (! Schema::hasColumn('ads', 'impressions')) {
                $table->unsignedBigInteger('impressions')->default(0)->after('end_at');
            }
            if (! Schema::hasColumn('ads', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('clicks');
            }
        });

        DB::table('ads')->whereNull('zone')->update([
            'zone' => DB::raw("CASE placement
                WHEN 'top' THEN 'header_banner'
                WHEN 'bottom' THEN 'footer_banner'
                WHEN 'sidebar' THEN 'sidebar_top'
                WHEN 'feed' THEN 'between_posts'
                WHEN 'blog_side' THEN 'sidebar_bottom'
                ELSE placement
            END"),
        ]);

        DB::table('ads')->whereNull('title')->update(['title' => DB::raw('name')]);
        DB::table('ads')->whereNull('custom_html')->where('type', 'script')->update(['custom_html' => DB::raw('content')]);
        DB::table('ads')->whereNull('impressions')->update(['impressions' => DB::raw('views')]);
        DB::table('ads')->whereNull('start_at')->update(['start_at' => DB::raw('starts_at')]);
        DB::table('ads')->whereNull('end_at')->update(['end_at' => DB::raw('ends_at')]);
        DB::table('ads')->where('type', 'image')->update(['type' => 'image_link']);
        DB::table('ads')->where('type', 'script')->update(['type' => 'custom_html']);
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            foreach (['zone', 'title', 'adsense_client', 'adsense_slot', 'adsense_format', 'custom_html', 'image_url', 'link_target', 'show_to', 'start_at', 'end_at', 'impressions', 'sort_order'] as $column) {
                if (Schema::hasColumn('ads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
