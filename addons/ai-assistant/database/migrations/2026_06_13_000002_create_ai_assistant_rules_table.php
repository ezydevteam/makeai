<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_rules', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 255);
            $table->text('response');
            $table->string('match_type', 32)->default('contains'); // 'exact' or 'contains'
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_rules');
    }
};
