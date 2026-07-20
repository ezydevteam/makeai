<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->string('tool_slug', 100)->nullable()->after('type');
            $table->string('request_id', 36)->nullable()->after('credits_used');
            $table->unsignedInteger('response_time_ms')->nullable()->after('request_id');
            $table->index('tool_slug');
        });

        // MySQL only: migrate existing metadata->tool_slug to the column
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE ai_usage_logs
                SET tool_slug = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.template_slug'))
                WHERE tool_slug IS NULL
                  AND metadata IS NOT NULL
                  AND JSON_EXTRACT(metadata, '$.template_slug') IS NOT NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['tool_slug']);
            $table->dropColumn(['tool_slug', 'request_id', 'response_time_ms']);
        });
    }
};
