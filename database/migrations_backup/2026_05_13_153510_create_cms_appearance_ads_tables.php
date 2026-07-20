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
        // CMS: Pages
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // CMS: Contacts
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // Appearance: Menus
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // top_header, footer, etc.
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->enum('type', ['url', 'page', 'route'])->default('url');
            $table->string('url')->nullable();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('route_name')->nullable();
            $table->string('target')->default('_self');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Appearance: Dynamic Settings
        Schema::create('appearance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope'); // admin, theme_default
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'key']);
        });

        // Ads System
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('zone')->unique(); // header_banner, sidebar_top, etc.
            $table->enum('type', ['adsense', 'custom_html', 'image_link']);
            $table->string('title')->nullable();
            $table->string('adsense_client')->nullable();
            $table->string('adsense_slot')->nullable();
            $table->longText('custom_html')->nullable();
            $table->string('image_url')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
        Schema::dropIfExists('appearance_settings');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('pages');
    }
};
