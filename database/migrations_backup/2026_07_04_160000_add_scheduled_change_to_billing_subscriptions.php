<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support scheduled plan downgrades: a subscriber keeps their current plan until
 * the period ends, then auto-switches to the scheduled (lower) plan. No charge,
 * no refund. Applied by the subscriptions:apply-scheduled-changes command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->foreignId('scheduled_plan_id')->nullable()->after('plan_id');
            $table->string('scheduled_billing_cycle')->nullable()->after('scheduled_plan_id');
            $table->timestamp('scheduled_change_at')->nullable()->after('scheduled_billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['scheduled_plan_id', 'scheduled_billing_cycle', 'scheduled_change_at']);
        });
    }
};
