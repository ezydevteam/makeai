<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aip_assets', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('guest_ip', 45)->nullable();

            $table->foreignId('folder_id')->nullable()->constrained('aip_folders')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('aip_jobs')->nullOnDelete();

            // Lineage: the asset this one was derived from. Self-referencing, so a
            // generate → upscale → remove-bg chain stays reconstructable.
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->string('source', 20)->default('generated'); // generated|uploaded|derived
            $table->string('operation', 40)->nullable();        // op that produced it

            $table->string('disk', 40)->default('public');
            $table->string('path', 500);
            $table->string('thumb_path', 500)->nullable();
            $table->string('mime', 60)->default('image/png');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);

            $table->text('prompt')->nullable();
            $table->text('negative_prompt')->nullable();
            $table->string('model', 100)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('seed', 40)->nullable();
            $table->json('params')->nullable();

            $table->boolean('is_favorite')->default(false);
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('aip_assets')->nullOnDelete();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['user_id', 'folder_id']);
            $table->index('parent_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aip_assets');
    }
};
