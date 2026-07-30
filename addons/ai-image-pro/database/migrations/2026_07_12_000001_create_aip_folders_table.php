<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aip_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('color', 7)->default('#6366f1');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aip_folders');
    }
};
