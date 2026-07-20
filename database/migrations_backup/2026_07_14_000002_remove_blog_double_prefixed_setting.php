<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'blog_blog_sidebar_position')->delete();
    }

    public function down(): void
    {
        // No-op
    }
};
