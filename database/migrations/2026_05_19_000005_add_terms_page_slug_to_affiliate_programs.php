<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_programs', 'terms_page_slug')) {
                $table->string('terms_page_slug')->nullable()->after('terms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            if (Schema::hasColumn('affiliate_programs', 'terms_page_slug')) {
                $table->dropColumn('terms_page_slug');
            }
        });
    }
};
