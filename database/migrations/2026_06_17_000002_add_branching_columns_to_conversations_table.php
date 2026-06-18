<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_conversation_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('branch_point_message_id')->nullable()->after('parent_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['parent_conversation_id', 'branch_point_message_id']);
        });
    }
};
