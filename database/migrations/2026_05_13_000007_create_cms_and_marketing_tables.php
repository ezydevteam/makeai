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
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->integer('posts_count')->unsigned()->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('blog_categories')->onDelete('set null');
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->bigInteger('author_id')->unsigned();
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
            $table->bigInteger('views_count')->unsigned()->default(0)->index();
            $table->smallInteger('reading_time')->unsigned()->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->enum('schema_type', ['Article', 'BlogPosting', 'NewsArticle'])->default('BlogPosting');
            $table->boolean('no_index')->default(false);
            $table->boolean('show_related_posts')->default(true);
            $table->boolean('show_toc')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('author_id')->references('id')->on('admins')->onDelete('cascade');
            $table->index(['status', 'published_at']);
            // Fulltext indexes are MySQL-only; sqlite (used in tests) can't build them and the
            // app falls back to LIKE search there. Guard so migrations stay sqlite-safe.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['title', 'excerpt', 'content'], 'live_search_blog_posts_fulltext');
            }
        });

        Schema::create('blog_post_categories', function (Blueprint $table) {
            $table->bigInteger('post_id')->unsigned();
            $table->bigInteger('category_id')->unsigned();

            $table->primary(['post_id', 'category_id']);
            $table->foreign('post_id')->references('id')->on('blog_posts')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('blog_categories')->onDelete('cascade');
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->integer('posts_count')->unsigned()->default(0);
            $table->timestamps();
        });

        Schema::create('blog_post_tags', function (Blueprint $table) {
            $table->bigInteger('post_id')->unsigned();
            $table->bigInteger('tag_id')->unsigned();

            $table->primary(['post_id', 'tag_id']);
            $table->foreign('post_id')->references('id')->on('blog_posts')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('blog_tags')->onDelete('cascade');
        });

        Schema::create('blog_post_views', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('post_id')->unsigned();
            $table->string('ip_address', 45);
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            $table->foreign('post_id')->references('id')->on('blog_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['post_id', 'viewed_at']);
            $table->index(['ip_address', 'viewed_at']);
        });

        Schema::create('blog_post_revisions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('post_id')->unsigned();
            $table->bigInteger('admin_id')->unsigned()->nullable();
            $table->string('title', 500);
            $table->longText('content');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('blog_posts')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
            $table->index(['post_id', 'created_at']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('commentable_type');
            $table->bigInteger('commentable_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'spam'])->default('pending');
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->integer('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id']);
            $table->index(['status', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade');
        });

        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('comment_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
            $table->unique(['comment_id', 'ip_hash']);
            $table->foreign('comment_id')->references('id')->on('comments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('comment_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->string('reason', 500)->nullable();

            $table->unique(['comment_id', 'user_id']);
            $table->unique(['comment_id', 'ip_hash']);
            $table->index('created_at');
            $table->foreign('comment_id')->references('id')->on('comments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('company')->nullable();
            $table->string('avatar', 500)->nullable();
            $table->text('content');
            $table->tinyInteger('rating')->default(5);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->enum('source', ['manual', 'google', 'trustpilot', 'ai'])->default('manual');
            // Whether this testimonial's source badge is shown on the public site. Off by
            // default so a seeded/imported testimonial doesn't advertise its origin unasked.
            $table->boolean('show_source')->default(false);
            $table->timestamps();
        });

        Schema::create('faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 500);
            $table->text('answer');
            $table->bigInteger('category_id')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('faq_categories')->onDelete('set null');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['topbar', 'popup', 'bottom_popup', 'notification'])->default('topbar');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('bg_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->string('cta_text', 100)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->enum('target_audience', ['all', 'guests', 'auth', 'free', 'pro'])->default('all');
            $table->string('trigger_type', 50)->nullable();
            $table->string('trigger_value', 50)->nullable();
            $table->enum('show_frequency', ['always', 'session', 'once'])->default('session');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('admins')->onDelete('set null');
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('status')->default('subscribed');
            $table->string('token', 64)->unique();
            $table->string('confirm_token', 64)->nullable()->unique();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->longText('content');
            $table->string('audience', 40)->default('subscribers');
            $table->timestamp('sent_at')->nullable();
            $table->integer('recipient_count')->default(0);
            $table->integer('sent_count')->unsigned()->default(0);
            $table->integer('failed_count')->unsigned()->default(0);
            $table->integer('opened_count')->default(0);
            $table->enum('status', ['draft', 'sending', 'sent'])->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->text('message');
            // Optional call-to-action appended to the body. With no label only the URL
            // is appended; with a label it reads "<label>: <url>".
            $table->string('action_url', 500)->nullable();
            $table->string('action_label', 80)->nullable();
            $table->integer('recipient_count')->unsigned()->default(0);
            $table->integer('sent_count')->unsigned()->default(0);
            $table->integer('failed_count')->unsigned()->default(0);
            $table->enum('status', ['draft', 'sending', 'sent'])->default('draft');
            $table->bigInteger('created_by_admin_id')->unsigned()->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by_admin_id')->references('id')->on('admins')->onDelete('set null');
            $table->index('status');
        });

        Schema::create('sms_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('campaign_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable();
            // E.164 number snapshotted at send time: the log must show what was
            // actually texted even if the user later changes their number.
            $table->string('phone', 32);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'user_id']);
            $table->foreign('campaign_id')->references('id')->on('sms_campaigns')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('newsletter_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('campaign_id')->unsigned();
            $table->bigInteger('subscriber_id')->unsigned()->nullable();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('email');
            $table->string('name')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'email']);
            $table->foreign('campaign_id')->references('id')->on('newsletter_campaigns')->onDelete('cascade');
            $table->foreign('subscriber_id')->references('id')->on('newsletter_subscribers')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'user_id']);
        });

        Schema::create('social_follow_counts', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->unique();
            $table->string('profile_url', 500)->nullable();
            $table->bigInteger('count')->unsigned()->default(0);
            $table->bigInteger('manual_count')->unsigned()->nullable();
            $table->string('count_source', 20)->default('manual');
            $table->boolean('fetch_enabled')->default(false);
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('ip_address')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->bigInteger('notifiable_id')->unsigned();
            $table->json('data');
            $table->enum('status', ['read', 'unread'])->default('unread')->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->string('subject', 500);
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(true);
            $table->boolean('requires_pro')->default(false);
            $table->enum('category', ['auth', 'account', 'subscription', 'newsletter', 'custom', 'affiliate', 'support', 'export', 'content', 'maintenance']);
            $table->bigInteger('last_edited_by')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('last_edited_by')->references('id')->on('admins')->onDelete('set null');
        });

        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('template_slug', 100)->nullable();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('subject', 500);
            $table->enum('status', ['sent', 'failed', 'bounced'])->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('social_follow_counts');
        Schema::dropIfExists('sms_campaign_recipients');
        Schema::dropIfExists('sms_campaigns');
        Schema::dropIfExists('newsletter_campaign_recipients');
        Schema::dropIfExists('newsletter_campaigns');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('faq_categories');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('comment_reports');
        Schema::dropIfExists('comment_likes');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('blog_post_revisions');
        Schema::dropIfExists('blog_post_views');
        Schema::dropIfExists('blog_post_tags');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_post_categories');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
