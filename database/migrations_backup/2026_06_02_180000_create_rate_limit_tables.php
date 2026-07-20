<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banned_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('banned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('banned_by')->nullable()->constrained('admins')->nullOnDelete();
        });

        Schema::create('user_rate_limit_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->integer('max_attempts');
            $table->integer('window_seconds');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rate_limit_overrides');
        Schema::dropIfExists('banned_ips');
    }
};
