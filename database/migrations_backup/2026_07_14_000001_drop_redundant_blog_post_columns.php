<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['show_author', 'show_date', 'show_reading_time', 'show_share_buttons']);
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->boolean('show_author')->default(true);
            $table->boolean('show_date')->default(true);
            $table->boolean('show_reading_time')->default(true);
            $table->boolean('show_share_buttons')->default(true);
        });
    }
};
