<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flat per-call credit charge for non-LLM tools (plagiarism, web search, stock
 * images, YouTube, scraping, grammar, AI-detector). These hit paid external APIs
 * but produce no token usage, so they can't be billed via the token path. NULL/0
 * means the tool is free. Charged via TokenGuard::chargeToolCredits().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->decimal('credit_cost', 10, 2)->nullable()->after('max_variants');
        });
    }

    public function down(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn('credit_cost');
        });
    }
};
