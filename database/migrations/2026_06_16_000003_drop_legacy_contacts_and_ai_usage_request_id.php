<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacts')) {
            Schema::dropIfExists('contacts');
        }

        if (Schema::hasTable('category_slug_history')) {
            Schema::dropIfExists('category_slug_history');
        }

        if (Schema::hasColumn('ai_usage_logs', 'request_id')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                $table->dropColumn('request_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('subject')->nullable();
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('category_slug_history')) {
            Schema::create('category_slug_history', function (Blueprint $table) {
                $table->id();
                $table->string('old_slug', 200);
                $table->string('new_slug', 200);
                $table->timestamp('changed_at')->useCurrent();
            });
        }

        if (! Schema::hasColumn('ai_usage_logs', 'request_id')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                $table->string('request_id', 36)->nullable()->after('credits_used');
            });
        }
    }
};
