<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ie_edits', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->unsignedBigInteger('ie_session_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('operation', [
                'inpaint', 'outpaint', 'bg_remove', 'upscale',
                'style_transfer', 'object_remove',
                'color_correction', 'text_overlay',
            ]);
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('provider', 30)->nullable();
            $table->string('input_path', 500);
            $table->string('output_path', 500)->nullable();
            $table->string('output_url', 500)->nullable();
            $table->string('mask_path', 500)->nullable();
            $table->json('params')->nullable();
            $table->decimal('credits_deducted', 10, 4)->default(0);
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->boolean('is_current')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('ie_session_id')
                ->references('id')
                ->on('ie_sessions')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['ie_session_id', 'version_number']);
            $table->index(['user_id', 'status']);
            $table->index('is_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ie_edits');
    }
};
