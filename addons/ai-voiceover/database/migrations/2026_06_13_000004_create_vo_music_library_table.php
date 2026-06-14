<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vo_music_library', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('file_path', 500);
            $table->string('file_url', 500)->nullable();
            $table->unsignedInteger('file_size_bytes')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('is_shared');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vo_music_library');
    }
};
