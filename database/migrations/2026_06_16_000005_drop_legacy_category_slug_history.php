<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('category_slug_history')) {
            Schema::dropIfExists('category_slug_history');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('category_slug_history')) {
            Schema::create('category_slug_history', function (Blueprint $table) {
                $table->id();
                $table->string('old_slug', 200);
                $table->string('new_slug', 200);
                $table->timestamp('changed_at')->useCurrent();
            });
        }
    }
};
