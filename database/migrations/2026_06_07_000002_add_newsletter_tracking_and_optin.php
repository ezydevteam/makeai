<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('confirm_token', 64)->nullable()->unique()->after('token');
            $table->string('status')->default('subscribed')->change();
        });

        Schema::table('newsletter_campaign_recipients', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_campaign_recipients', function (Blueprint $table) {
            $table->dropColumn('opened_at');
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn('confirm_token');
            $table->string('status')->default('subscribed')->change();
        });
    }
};
