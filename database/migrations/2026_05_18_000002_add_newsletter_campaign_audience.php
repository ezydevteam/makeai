<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_campaigns', function (Blueprint $table) {
            $table->string('audience', 40)->default('subscribers')->after('content');
        });

        Schema::table('newsletter_campaign_recipients', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('subscriber_id')->constrained('users')->nullOnDelete();
            $table->index(['campaign_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('newsletter_campaigns', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
