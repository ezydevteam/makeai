<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('knowledge_base_chunks', function ($table) {
            $table->fullText('text', 'chunks_text_fulltext');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('knowledge_base_chunks', function ($table) {
            $table->dropFullText('chunks_text_fulltext');
        });
    }
};
