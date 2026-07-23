<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Opt-in lead/standfirst: render the excerpt under the title on the
            // public page. Defaults to false so existing pages keep their look.
            $table->boolean('show_excerpt')->default(false)->after('show_title');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('show_excerpt');
        });
    }
};
