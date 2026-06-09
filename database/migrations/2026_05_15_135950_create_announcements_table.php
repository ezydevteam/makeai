<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['topbar', 'popup', 'notification'])->default('topbar');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('bg_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->string('cta_text', 100)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->enum('target_audience', ['all', 'guests', 'auth', 'free', 'pro'])->default('all');
            $table->string('trigger_type', 50)->nullable(); // onload, exit, delay, scroll
            $table->string('trigger_value', 50)->nullable(); // delay seconds or scroll %
            $table->enum('show_frequency', ['always', 'session', 'once'])->default('session');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
