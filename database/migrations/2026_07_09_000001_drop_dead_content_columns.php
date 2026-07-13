<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop verified-dead columns across the CMS content tables. Each was written by
 * app code but never read/displayed anywhere (see the content dead-column audit):
 *
 *  faq_categories.slug        — generated with a uniqid() suffix on every save,
 *                               never read, routed, or displayed.
 *  comments.ip_address        — captured on every comment; never read
 *                               (rate-limiting uses the live request IP).
 *  comment_reports.reason     — collected in the report modal but never surfaced
 *  comment_reports.details      to admins (only reports_count is shown).
 *  comment_reports.status     — always written 'open'; never queried or transitioned.
 *  blog_categories.og_image   — in $fillable/request but no admin input and no reader.
 *
 * Guarded (hasColumn / hasTable) so it is safe on installs that already lack them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('faq_categories') && Schema::hasColumn('faq_categories', 'slug')) {
            Schema::table('faq_categories', function (Blueprint $table) {
                // slug carries a unique index — drop it before the column.
                try {
                    $table->dropUnique('faq_categories_slug_unique');
                } catch (\Throwable) {
                    // Index name may differ or already be gone; ignore.
                }
                $table->dropColumn('slug');
            });
        }

        if (Schema::hasTable('comments') && Schema::hasColumn('comments', 'ip_address')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->dropColumn('ip_address');
            });
        }

        if (Schema::hasTable('comment_reports')) {
            Schema::table('comment_reports', function (Blueprint $table) {
                // status is part of a composite index — drop it before the column.
                if (Schema::hasColumn('comment_reports', 'status')) {
                    try {
                        $table->dropIndex('comment_reports_status_created_at_index');
                    } catch (\Throwable) {
                        // Index name may differ or already be gone; ignore.
                    }
                }
                foreach (['reason', 'details', 'status'] as $column) {
                    if (Schema::hasColumn('comment_reports', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('blog_categories') && Schema::hasColumn('blog_categories', 'og_image')) {
            Schema::table('blog_categories', function (Blueprint $table) {
                $table->dropColumn('og_image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('faq_categories') && ! Schema::hasColumn('faq_categories', 'slug')) {
            Schema::table('faq_categories', function (Blueprint $table) {
                $table->string('slug', 100)->nullable()->unique();
            });
        }

        if (Schema::hasTable('comments') && ! Schema::hasColumn('comments', 'ip_address')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->string('ip_address', 45)->nullable();
            });
        }

        if (Schema::hasTable('comment_reports')) {
            Schema::table('comment_reports', function (Blueprint $table) {
                if (! Schema::hasColumn('comment_reports', 'reason')) {
                    $table->string('reason', 100)->nullable();
                }
                if (! Schema::hasColumn('comment_reports', 'details')) {
                    $table->text('details')->nullable();
                }
                if (! Schema::hasColumn('comment_reports', 'status')) {
                    $table->enum('status', ['open', 'reviewed', 'dismissed'])->default('open');
                }
            });
        }

        if (Schema::hasTable('blog_categories') && ! Schema::hasColumn('blog_categories', 'og_image')) {
            Schema::table('blog_categories', function (Blueprint $table) {
                $table->string('og_image', 500)->nullable();
            });
        }
    }
};
