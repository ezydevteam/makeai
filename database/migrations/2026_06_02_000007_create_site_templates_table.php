<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->string('tagline', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('preview_image', 500)->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('layout_component', 100);
            $table->json('bundled_tool_slugs')->nullable();
            $table->boolean('requires_pro')->default(false);

            $table->string('color_primary', 20)->nullable();
            $table->string('color_secondary', 20)->nullable();
            $table->string('color_bg', 20)->nullable();
            $table->string('color_surface', 20)->nullable();
            $table->string('color_text', 20)->nullable();
            $table->string('font_heading', 100)->nullable();
            $table->string('font_body', 100)->nullable();

            $table->string('hero_headline', 500)->nullable();
            $table->text('hero_subheadline')->nullable();
            $table->string('hero_cta_text', 100)->nullable();
            $table->string('hero_cta_url', 500)->nullable();
            $table->string('hero_bg_image', 500)->nullable();

            $table->text('custom_html_head')->nullable();
            $table->text('custom_html_body')->nullable();
            $table->text('custom_css')->nullable();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image', 500)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_templates');
    }
};
