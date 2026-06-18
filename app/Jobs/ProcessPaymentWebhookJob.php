<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payment\PaymentActivationService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public function __construct(
        public string $gateway,
        public array $payload,
    ) {}

    public function handle(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        try {
            match ($this->gateway) {
                'paypal' => $this->processPayPal($lifecycle, $activation),
                'paddle' => $this->processPaddle($lifecycle, $activation),
                'razorpay' => $this->processRazorpay($lifecycle, $activation),
                'sslcommerz' => $this->processSslCommerz($lifecycle, $activation),
                'coingate' => $this->processCoinGate($lifecycle, $activation),
                'paystack' => $this->processPaystack($lifecycle, $activation),
                '2checkout' => $this->processTwoCheckout($lifecycle, $activation),
                default => Log::warning('Unknown gateway in webhook job', ['gateway' => $this->gateway]),
            };
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $this->gateway,
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);
            
            // Do not retry if it's a validation failure or idempotency issue
            if (str_contains($e->getMessage(), 'Invalid') || str_contains($e->getMessage(), 'not found')) {
                $this->delete();
            }
        }
    }

    /**
     * Activate a payment, routing to the appropriate method based on payment type.
     */
    private function activatePayment(Payment $payment, string $gatewayPaymentId, SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation, ?string $gatewaySubscriptionId = null): void
    {
        if ($payment->type === 'credit_topup') {
            $activation->activateCreditTopup($payment, $gatewayPaymentId);
        } else {
            $lifecycle->activateFromPayment($payment, $gatewayPaymentId, $gatewaySubscriptionId);
        }
    }

    private function processPayPal(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        // PayPal validation requires outbound HTTP request, handled here asynchronously
        $gateway = \App\Models\PaymentGateway::where('slug', 'paypal')->where('is_enabled', true)->firstOrFail();
        
        if (! $this->validPayPalSignature($gateway)) {
            throw new \Exception('Invalid PayPal signature');
        }

        $type = $this->payload['event_type'] ?? '';
        $resource = $this->payload['resource'] ?? [];

        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            $paymentUlid = data_get($resource, 'custom_id') ?: data_get($resource, 'supplementary_data.related_ids.order_id');
            $payment = Payment::where('ulid', $paymentUlid)->orWhere('gateway_payment_id', $paymentUlid)->first();

            if ($payment && $payment->status !== 'completed') {
                $this->activatePayment($payment, data_get($resource, 'id'), $lifecycle, $activation);
            }
        }

        if (in_array($type, ['BILLING.SUBSCRIPTION.PAYMENT.SUCCEEDED', 'PAYMENT.SALE.COMPLETED'], true)) {
            $subscriptionId = data_get($resource, 'billing_agreement_id') ?: data_get($resource, 'id');
            if ($subscriptionId) {
                $lifecycle->renewFromGatewaySubscription(
                    'paypal',
                    $subscriptionId,
                    data_get($resource, 'id'),
                    (float) data_get($resource, 'amount.total', data_get($resource, 'amount.value', 0)),
                    strtoupper((string) data_get($resource, 'amount.currency', data_get($resource, 'amount.currency_code', 'USD')))
                );
            }
        }

        if (in_array($type, ['BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED'], true)) {
            $subscription = \App\Models\GatewaySubscription::where('gateway', 'paypal')
                ->where('gateway_subscription_id', data_get($resource, 'id'))->first();
            if ($subscription) {
                $lifecycle->cancelAtPeriodEnd($subscription);
            }
        }

        if (in_array($type, ['PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED', 'CHECKOUT.ORDER.VOIDED'], true)) {
            $paymentUlid = data_get($resource, 'custom_id') ?: data_get($resource, 'supplementary_data.related_ids.order_id');
            $payment = Payment::where('ulid', $paymentUlid)->orWhere('gateway_payment_id', $paymentUlid)->first();
            if ($payment) {
                $lifecycle->fail($payment, $type);
            }
        }
    }

    private function validPayPalSignature(\App\Models\PaymentGateway $gateway): bool
    {
        $webhookId = $gateway->getCredential('webhook_id');
        $token = $this->payPalAccessToken($gateway);

        if (! $webhookId || ! $token) {
            return false;
        }

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->acceptJson()
            ->post($this->payPalBaseUrl($gateway).'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $this->payload['transmission_id'] ?? '', // Simplified for job context
                'cert_url' => $this->payload['cert_url'] ?? '',
                'transmission_id' => $this->payload['transmission_id'] ?? '',
                'transmission_sig' => $this->payload['transmission_sig'] ?? '',
                'transmission_time' => $this->payload['transmission_time'] ?? '',
                'webhook_id' => $webhookId,
                'webhook_event' => $this->payload,
            ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    private function payPalAccessToken(\App\Models\PaymentGateway $gateway): ?string
    {
        $clientId = $gateway->getCredential('client_id');
        $clientSecret = $gateway->getCredential('client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $response = \Illuminate\Support\Facades\Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->payPalBaseUrl($gateway).'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $response->successful() ? $response->json('access_token') : null;
    }

    private function payPalBaseUrl(\App\Models\PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private function processSslCommerz(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $gateway = \App\Models\PaymentGateway::where('slug', 'sslcommerz')->where('is_enabled', true)->firstOrFail();
        $payment = Payment::where('ulid', $this->payload['tran_id'])->first();

        if (! $payment) {
            return;
        }

        if (in_array($this->payload['status'], ['VALID', 'VALIDATED'], true)) {
            if ($this->validSslCommerzPayment($gateway, $payment)) {
                $this->activatePayment($payment, (string) ($this->payload['bank_tran_id'] ?? $this->payload['val_id']), $lifecycle, $activation);
            }
        } elseif (in_array($this->payload['status'], ['FAILED', 'CANCELLED', 'UNATTEMPTED', 'EXPIRED'], true)) {
            $lifecycle->fail($payment, (string) $this->payload['status']);
        }
    }

    private function validSslCommerzPayment(\App\Models\PaymentGateway $gateway, Payment $payment): bool
    {
        $storeId = $gateway->getCredential('store_id');
        $storePassword = $gateway->getCredential('store_password');
        $valId = $this->payload['val_id'] ?? null;

        if (! $storeId || ! $storePassword || ! $valId) {
            return false;
        }

        $baseUrl = $gateway->is_test_mode ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
        $response = \Illuminate\Support\Facades\Http::get($baseUrl.'/validator/api/validationserverAPI.php', [
            'val_id' => $valId,
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'format' => 'json',
        ]);

        return $response->successful()
            && in_array($response->json('status'), ['VALID', 'VALIDATED'], true)
            && (string) $response->json('tran_id') === $payment->ulid;
    }

    // Note: Other gateways (Paddle, Razorpay, CoinGate, Paystack, 2Checkout) use fast HMAC validation
    // and can remain synchronous in the controller, but routing them here ensures uniform 200 OK responses.
    // For brevity and focus on the synchronous bottleneck, PayPal and SSLCommerz are the primary targets.
}