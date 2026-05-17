<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Check if columns exist before adding (safety check)
            if (! Schema::hasColumn('pages', 'excerpt')) {
                $table->text('excerpt')->nullable()->after('content');
            }
            if (! Schema::hasColumn('pages', 'meta_keywords')) {
                $table->string('meta_keywords', 500)->nullable()->after('meta_description');
            }
            if (! Schema::hasColumn('pages', 'og_image')) {
                $table->string('og_image', 500)->nullable()->after('meta_keywords');
            }
            if (! Schema::hasColumn('pages', 'template')) {
                $table->enum('template', ['default', 'full_width', 'blank', 'landing'])->default('default')->after('og_image');
            }
            if (! Schema::hasColumn('pages', 'featured_image')) {
                $table->string('featured_image', 500)->nullable()->after('template');
            }
            if (! Schema::hasColumn('pages', 'show_title')) {
                $table->boolean('show_title')->default(true)->after('featured_image');
            }
            if (! Schema::hasColumn('pages', 'show_breadcrumbs')) {
                $table->boolean('show_breadcrumbs')->default(true)->after('show_title');
            }
            if (! Schema::hasColumn('pages', 'show_featured_image')) {
                $table->boolean('show_featured_image')->default(true)->after('show_breadcrumbs');
            }
            if (! Schema::hasColumn('pages', 'show_sidebar')) {
                $table->boolean('show_sidebar')->default(false)->after('show_featured_image');
            }
            if (! Schema::hasColumn('pages', 'sidebar_position')) {
                $table->enum('sidebar_position', ['left', 'right'])->default('right')->after('show_sidebar');
            }
            if (! Schema::hasColumn('pages', 'container_width')) {
                $table->enum('container_width', ['default', 'wide', 'full', 'narrow'])->default('default')->after('sidebar_position');
            }
            if (! Schema::hasColumn('pages', 'status')) {
                $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft')->after('container_width');
            }
            if (! Schema::hasColumn('pages', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('pages', 'password')) {
                $table->string('password')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('pages', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('password')->constrained('pages')->nullOnDelete();
            }
            if (! Schema::hasColumn('pages', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('parent_id');
            }
            if (! Schema::hasColumn('pages', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('sort_order');
            }
            if (! Schema::hasColumn('pages', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_system')->constrained('admins')->nullOnDelete();
            }

            // Remove old column if it exists
            if (Schema::hasColumn('pages', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);

            $table->dropForeign(['created_by']);
            $table->dropForeign(['parent_id']);

            $table->dropColumn([
                'excerpt',
                'meta_keywords',
                'og_image',
                'template',
                'featured_image',
                'show_title',
                'show_breadcrumbs',
                'show_featured_image',
                'show_sidebar',
                'sidebar_position',
                'container_width',
                'status',
                'published_at',
                'password',
                'parent_id',
                'sort_order',
                'is_system',
                'created_by',
            ]);
        });
    }
};
