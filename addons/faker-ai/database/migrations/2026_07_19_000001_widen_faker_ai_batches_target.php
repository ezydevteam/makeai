<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A batch may now target MANY items (a JSON list of slugs/ulids) rather than one, so the
 * 191-char column can overflow. Widen both it and the human label to TEXT.
 *
 * Legacy rows hold a bare string ('*' or a single slug) — FakerBatch::targetList() reads both
 * shapes, so nothing needs rewriting here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faker_ai_batches', function (Blueprint $table) {
            $table->text('target')->nullable()->change();
            $table->text('target_label')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('faker_ai_batches', function (Blueprint $table) {
            $table->string('target', 191)->nullable()->change();
            $table->string('target_label', 191)->nullable()->change();
        });
    }
};
