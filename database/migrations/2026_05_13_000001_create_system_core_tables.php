<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->enum('type', ['string', 'boolean', 'integer', 'json', 'encrypted'])->default('string');
            $table->string('group', 100)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('symbol', 10);
            $table->string('name', 100);
            $table->decimal('exchange_rate', 12, 6)->default('1.000000');
            $table->tinyInteger('decimal_places')->unsigned()->default(2);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('flag')->nullable();
            $table->boolean('is_rtl')->default(false);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('date_format', 50)->default('MMM D, YYYY');
            $table->string('time_format', 50)->default('h:mm A');
            $table->string('number_system', 20)->default('latn');
            $table->timestamps();
        });

        // Per-language UI strings. The composite index is prefixed on MySQL because `key` is
        // TEXT and cannot be indexed whole; sqlite has no prefix syntax and no key-length
        // limit, so it indexes the column directly.
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->text('key');
            $table->text('value');
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX translations_lang_key_index ON translations (language_id, `key`(191))');
        } else {
            Schema::table('translations', function (Blueprint $table) {
                $table->index(['language_id'], 'translations_lang_key_index');
            });
        }

        Schema::create('password_history', function (Blueprint $table) {
            $table->id();
            $table->string('user_type');
            $table->bigInteger('user_id')->unsigned();
            $table->string('password');
            $table->timestamps();

            $table->index(['user_type', 'user_id']);
            $table->index(['user_type', 'user_id', 'created_at']);
        });

        // Sign-in audit trail for end users (the admin equivalent is admin_login_history).
        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->text('user_agent')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('success')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['success', 'created_at']);
            $table->index(['country', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_history');
        Schema::dropIfExists('password_history');
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('settings');
    }
};
