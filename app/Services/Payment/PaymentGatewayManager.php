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

    public function processingFee(PaymentGateway $gateway, float $amount): float
    {
        if (! $gateway->processing_fee_type || ! $gateway->processing_fee_value) {
            return 0;
        }

        return $gateway->processing_fee_type === 'percentage'
            ? round($amount * ((float) $gateway->processing_fee_value / 100), 2)
            : (float) $gateway->processing_fee_value;
    }

    public function totalWithFee(PaymentGateway $gateway, float $amount): float
    {
        return round($amount + $this->processingFee($gateway, $amount), 2);
    }
}
