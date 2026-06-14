<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->enum('source_type', ['generated', 'uploaded', 'url'])->default('generated');
            $table->unsignedBigInteger('source_image_id')->nullable();
            $table->string('source_path', 500);
            $table->string('source_url', 500)->nullable();
            $table->string('current_path', 500);
            $table->string('current_url', 500)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('format', 10)->default('png');
            $table->string('last_operation', 50)->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('source_image_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_sessions');
    }
};
