<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->unsignedInteger('posts_count')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedInteger('posts_count')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('author_id')->constrained('admins')->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('slug', 500)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image', 500)->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->enum('status', ['draft', 'published', 'scheduled', 'private'])->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_sticky')->default(false)->index();
            $table->boolean('allow_comments')->default(true);
            $table->unsignedBigInteger('views_count')->default(0)->index();
            $table->unsignedSmallInteger('reading_time')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->enum('schema_type', ['Article', 'BlogPosting', 'NewsArticle'])->default('BlogPosting');
            $table->boolean('no_index')->default(false);
            $table->boolean('show_author')->default(true);
            $table->boolean('show_date')->default(true);
            $table->boolean('show_reading_time')->default(true);
            $table->boolean('show_share_buttons')->default(true);
            $table->boolean('show_related_posts')->default(true);
            $table->boolean('show_toc')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        Schema::create('blog_post_categories', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('blog_categories')->cascadeOnDelete();
            $table->primary(['post_id', 'category_id']);
        });

        Schema::create('blog_post_tags', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('blog_tags')->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('blog_post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['post_id', 'viewed_at']);
            $table->index(['ip_address', 'viewed_at']);
        });

        Schema::create('blog_post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('title', 500);
            $table->longText('content');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_revisions');
        Schema::dropIfExists('blog_post_views');
        Schema::dropIfExists('blog_post_tags');
        Schema::dropIfExists('blog_post_categories');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
