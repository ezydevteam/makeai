<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog posts track views_count but never had a share counter. FakerAI can seed one for
 * demos, and the public post can surface it beside views. Kept idempotent so the column is
 * safe to add whether or not the addon shipped it before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('blog_posts', 'share_count')) {
            return;
        }

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('share_count')->default(0)->after('views_count');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('blog_posts', 'share_count')) {
            return;
        }

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('share_count');
        });
    }
};
