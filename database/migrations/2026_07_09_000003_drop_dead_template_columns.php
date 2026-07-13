<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the inert `template` columns on `pages` and `blog_posts`.
 *
 * Both were admin-editable (a "Template" select in each editor; pages even showed
 * it as a column in the admin list) and validated as a required enum, but NO
 * rendering path ever read the value: the public Page.vue drives layout entirely
 * from container_width + show_sidebar/sidebar_position, and Blog/Show.vue uses
 * fixed markup. The selector implied a layout choice that did nothing, so the
 * column and its UI are being removed rather than half-wired.
 *
 * Guarded (hasColumn) so it is safe on installs that already lack the columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages') && Schema::hasColumn('pages', 'template')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropColumn('template');
            });
        }

        if (Schema::hasTable('blog_posts') && Schema::hasColumn('blog_posts', 'template')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->dropColumn('template');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages') && ! Schema::hasColumn('pages', 'template')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->enum('template', ['default', 'full_width', 'blank', 'landing'])->default('default');
            });
        }

        if (Schema::hasTable('blog_posts') && ! Schema::hasColumn('blog_posts', 'template')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->enum('template', ['default', 'full_width', 'sidebar_left', 'sidebar_right', 'no_sidebar'])->default('default');
            });
        }
    }
};
