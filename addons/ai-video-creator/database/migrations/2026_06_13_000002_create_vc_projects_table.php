<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vc_projects', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255)->default('Untitled Project');
            $table->text('description')->nullable();
            $table->foreignId('folder_id')->nullable()->constrained('vc_folders')->nullOnDelete();
            $table->string('color', 7)->default('#6366f1');
            $table->string('thumbnail_path', 500)->nullable();
            $table->unsignedInteger('render_count')->default(0);
            $table->unsignedInteger('total_duration')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vc_projects');
    }
};
