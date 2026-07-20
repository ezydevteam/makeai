<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_history', function (Blueprint $table) {
            $table->id();
            $table->morphs('user'); // user_id and user_type (for both User and Admin)
            $table->string('password');
            $table->timestamps();

            $table->index(['user_type', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_history');
    }
};