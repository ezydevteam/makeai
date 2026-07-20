<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_export_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'processing', 'ready', 'downloaded', 'expired'])->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });

        $this->createExportCenterTables();
    }

    /**
     * Export Center: saved column/filter presets and their scheduled counterparts. Both are
     * admin-owned, so they cascade with the admin that created them.
     */
    private function createExportCenterTables(): void
    {
        Schema::create('export_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('name');
            $table->string('dataset');
            $table->string('format')->default('xlsx');
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'dataset']);
        });

        Schema::create('scheduled_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('name');
            $table->string('dataset');
            $table->string('format')->default('xlsx');
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->string('frequency')->default('weekly');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->index('next_run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_exports');
        Schema::dropIfExists('export_presets');
        Schema::dropIfExists('data_export_requests');
    }
};
