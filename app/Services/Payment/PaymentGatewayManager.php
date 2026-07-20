<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;

class PaymentGatewayManager
{
    public function enabled()
    {
        return PaymentGateway::where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * The gateway's processing fee for a charge of $amount, in the store base currency.
     *
     * 'none' is a real value of the processing_fee_type enum (and its DB default), and
     * a non-empty string is TRUTHY — so the old `! $gateway->processing_fee_type` guard
     * did not catch it and fell through to the fixed-fee branch. An admin who selected
     * "None" but left a value in the field was silently charging it on every order.
     * Only the two fee types that actually mean something are honoured here.
     *
     * There is also no fee on a zero charge (free trial, 100%-off coupon, fully
     * prorated upgrade) — a fixed fee would otherwise be the entire "total".
     */
    public function processingFee(PaymentGateway $gateway, float $amount): float
    {
        if (! in_array($gateway->processing_fee_type, ['percentage', 'fixed'], true)) {
            return 0.0;
        }

        $value = (float) $gateway->processing_fee_value;

        if ($value <= 0 || $amount <= 0) {
            return 0.0;
        }

        return $gateway->processing_fee_type === 'percentage'
            ? round($amount * ($value / 100), 2)
            : round($value, 2);
    }

    public function totalWithFee(PaymentGateway $gateway, float $amount): float
    {
        return round($amount + $this->processingFee($gateway, $amount), 2);
    }
}
