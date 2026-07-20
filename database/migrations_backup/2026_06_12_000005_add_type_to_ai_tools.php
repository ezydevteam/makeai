<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->string('type', 50)->default('text')->after('slug');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
