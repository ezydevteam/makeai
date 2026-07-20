<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Languages and translations tables — full i18n support.
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();       // en, bn, ar
            $table->string('name', 100);                 // English, বাংলা
            $table->string('flag')->nullable();          // uploaded public image path
            $table->boolean('is_rtl')->default(false);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->text('key');                         // text hash or slug
            $table->text('value');                       // translated text
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            // MySQL needs a prefix length when indexing text columns.
            DB::statement('ALTER TABLE translations ADD INDEX translations_lang_key_index (language_id, `key`(191))');

            return;
        }

        Schema::table('translations', function (Blueprint $table) {
            $table->index(['language_id', 'key'], 'translations_lang_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }
};
