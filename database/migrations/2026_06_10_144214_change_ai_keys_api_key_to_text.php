<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_keys', function (Blueprint $table) {
            $table->text('api_key')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_keys', function (Blueprint $table) {
            $table->string('api_key')->change();
        });
    }
};
