<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow guest (unauthenticated) conversations: user_id becomes nullable and a
 * session_id identifies a guest's chats. Lives in the ai-chatbot addon so it
 * only runs alongside the conversations table it owns — never against a core
 * DB where the addon (and the table) may not be installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            if (! Schema::hasColumn('conversations', 'session_id')) {
                $table->string('session_id', 100)->nullable()->after('user_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'session_id')) {
                $table->dropIndex(['session_id']);
                $table->dropColumn('session_id');
            }

            $table->dropForeign(['user_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
