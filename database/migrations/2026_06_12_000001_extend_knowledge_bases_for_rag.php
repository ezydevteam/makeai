<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_bases', function (Blueprint $table) {
            $table->boolean('is_ephemeral')->default(false)->after('description');
            $table->string('source_tool', 100)->nullable()->after('is_ephemeral');
            $table->timestamp('expires_at')->nullable()->after('source_tool');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_bases', function (Blueprint $table) {
            $table->dropColumn(['is_ephemeral', 'source_tool', 'expires_at']);
        });
    }
};
