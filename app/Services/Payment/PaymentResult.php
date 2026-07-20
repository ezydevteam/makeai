<?php

namespace App\Services\Payment;

/**
 * PaymentResult — returned by createOneTimePayment().
 */
class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $paymentId = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $error = null,
    ) {}
}
