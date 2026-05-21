<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_follow_counts', function (Blueprint $table) {
            $table->string('profile_url', 500)->nullable()->after('platform');
            $table->unsignedBigInteger('manual_count')->nullable()->after('count');
            $table->string('count_source', 20)->default('manual')->after('manual_count');
            $table->boolean('fetch_enabled')->default(false)->after('count_source');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('fetch_enabled');
            $table->timestamp('last_fetched_at')->nullable()->after('sort_order');
            $table->text('last_error')->nullable()->after('last_fetched_at');

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('social_follow_counts', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropColumn([
                'profile_url',
                'manual_count',
                'count_source',
                'fetch_enabled',
                'sort_order',
                'last_fetched_at',
                'last_error',
            ]);
        });
    }
};
