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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('featured_image', 500)->nullable();
            $table->boolean('show_title')->default(true);
            $table->boolean('center_title')->default(false);
            $table->boolean('show_breadcrumbs')->default(true);
            $table->boolean('show_featured_image')->default(true);
            $table->boolean('show_sidebar')->default(false);
            $table->enum('sidebar_position', ['left', 'right'])->default('right');
            $table->string('container_width', 50)->default('1280px');
            $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('password')->nullable();
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('pages')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('set null');
            // Fulltext indexes are MySQL-only; sqlite (used in tests) can't build them and the
            // app falls back to LIKE search there. Guard so migrations stay sqlite-safe.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText('title', 'live_search_pages_fulltext');
            }
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('menu_id')->unsigned();
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->string('label');
            $table->string('description')->nullable();
            $table->enum('type', ['url', 'page', 'route'])->default('url');
            $table->string('url')->nullable();
            $table->bigInteger('page_id')->unsigned()->nullable();
            $table->string('route_name')->nullable();
            $table->string('target')->default('_self');
            $table->string('icon')->nullable();
            $table->string('badge_text', 50)->nullable();
            $table->string('badge_color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('requires_auth', 20)->default('none');
            $table->boolean('mega_menu')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menus')->onDelete('cascade');
            $table->foreign('page_id')->references('id')->on('pages')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('menu_items')->onDelete('cascade');
        });

        Schema::create('appearance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'key']);
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['adsense', 'custom_html', 'image_link'])->default('image_link');
            $table->string('zone', 100)->nullable();
            $table->string('title')->nullable();
            $table->string('adsense_client', 100)->nullable();
            $table->string('adsense_slot', 50)->nullable();
            $table->string('adsense_format', 50)->nullable();
            $table->longText('custom_html')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_target', 20)->default('_blank');
            $table->string('show_to', 30)->default('all');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->bigInteger('impressions')->unsigned()->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('clicks')->default(0);
            $table->integer('sort_order')->unsigned()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
        Schema::dropIfExists('appearance_settings');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('pages');
    }
};
