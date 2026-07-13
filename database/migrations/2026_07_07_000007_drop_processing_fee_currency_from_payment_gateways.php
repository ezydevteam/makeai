<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove `payment_gateways.processing_fee_currency`. The processing fee is always
 * denominated in the store base currency — a percentage fee is inherently in the
 * charged amount's currency, and a fixed fee is a flat amount added to (and shown
 * in) that same base currency. The column was never read by the fee calculation
 * (PaymentGatewayManager::processingFee) nor by the fee display, so it was dead and
 * actively misleading (a "fixed fee in another currency" was never converted).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_gateways', 'processing_fee_currency')) {
            Schema::table('payment_gateways', function (Blueprint $table) {
                $table->dropColumn('processing_fee_currency');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payment_gateways', 'processing_fee_currency')) {
            Schema::table('payment_gateways', function (Blueprint $table) {
                $table->string('processing_fee_currency', 3)->default('USD');
            });
        }
    }
};
