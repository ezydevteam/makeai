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
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 255);
            $table->string('subject', 500);
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(true);
            $table->boolean('requires_pro')->default(false);
            $table->enum('category', ['auth', 'account', 'subscription', 'newsletter', 'custom']);
            $table->foreignId('last_edited_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
