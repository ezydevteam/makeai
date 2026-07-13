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
        // 1. Newsletter
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->enum('status', ['subscribed', 'unsubscribed', 'bounced'])->default('subscribed');
            $table->string('token', 64)->unique();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->longText('content');
            $table->timestamp('sent_at')->nullable();
            $table->integer('recipient_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->enum('status', ['draft', 'sending', 'sent'])->default('draft');
            $table->timestamps();
        });

        // 2. Comments
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'spam'])->default('pending');
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->integer('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Favorites
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('favoriteable');
            $table->timestamps();

            $table->unique(['user_id', 'favoriteable_type', 'favoriteable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('newsletter_campaigns');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
