<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;

class PaymentGatewayManager
{
    /**
     * @return Collection<int, PaymentGateway>
     */
    public function enabled(): Collection
    {
        return PaymentGateway::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function processingFee(PaymentGateway $gateway, float $amount): float
    {
        if ($gateway->processing_fee_type === 'percentage') {
            return round($amount * ((float) $gateway->processing_fee_value / 100), 2);
        }

        if ($gateway->processing_fee_type === 'fixed') {
            return round((float) $gateway->processing_fee_value, 2);
        }

        return 0.0;
    }

    public function totalWithFee(PaymentGateway $gateway, float $amount): float
    {
        return round($amount + $this->processingFee($gateway, $amount), 2);
    }
}
