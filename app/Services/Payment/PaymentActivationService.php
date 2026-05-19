<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\Subscription\SubscriptionLifecycleService;

class PaymentActivationService extends SubscriptionLifecycleService
{
    public function activate(Payment $payment, ?string $gatewayPaymentId = null, ?string $gatewaySubscriptionId = null): Payment
    {
        return $this->activateFromPayment($payment, $gatewayPaymentId, $gatewaySubscriptionId);
    }
}
