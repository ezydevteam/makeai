<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('platform', ['instagram', 'facebook', 'twitter', 'linkedin', 'tiktok', 'pinterest', 'youtube']);
            $table->string('platform_user_id', 100);
            $table->string('platform_username', 100)->nullable();
            $table->string('platform_name', 150)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('page_id', 100)->nullable();
            $table->string('page_name', 150)->nullable();
            $table->enum('account_type', ['personal', 'page', 'business'])->default('personal');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('follower_count')->default(0);
            $table->timestamp('followers_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'platform_user_id'], 'ss_social_accounts_unique');
            $table->index(['user_id', 'platform', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_social_accounts');
    }
};
