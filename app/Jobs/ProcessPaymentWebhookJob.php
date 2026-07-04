<?php

namespace App\Jobs;

use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentActivationService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 3;

    public $backoff = 60;

    public function __construct(
        public string $gateway,
        public array $payload,
        public string $rawBody = '',
        public array $headers = [],
    ) {}

    public function handle(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
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
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Webhook processing failed permanently', [
            'gateway' => $this->gateway,
            'error' => $e->getMessage(),
            'payload' => $this->payload,
        ]);
    }

    private function header(string $key): string
    {
        return (string) ($this->headers[strtolower($key)] ?? '');
    }

    private function gatewayRecord(string $slug): ?PaymentGateway
    {
        // Intentionally not filtered by is_enabled: a payment already charged by the
        // gateway must still be processed even if the admin disabled it afterwards.
        $gateway = PaymentGateway::where('slug', $slug)->first();

        if (! $gateway) {
            Log::warning('Webhook received for unconfigured gateway', ['gateway' => $slug]);
        }

        return $gateway;
    }

    private function rejectInvalidSignature(string $gateway): void
    {
        Log::warning('Webhook rejected: invalid signature', [
            'gateway' => $gateway,
            'ip_headers' => [
                'x-forwarded-for' => $this->header('x-forwarded-for'),
            ],
        ]);
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

    // ─── PayPal ─────────────────────────────────

    private function processPayPal(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $gateway = $this->gatewayRecord('paypal');

        if (! $gateway) {
            return;
        }

        if (! $this->validPayPalSignature($gateway)) {
            $this->rejectInvalidSignature('paypal');

            return;
        }

        $type = $this->payload['event_type'] ?? '';
        $resource = $this->payload['resource'] ?? [];

        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            $paymentUlid = data_get($resource, 'custom_id') ?: data_get($resource, 'supplementary_data.related_ids.order_id');
            $payment = Payment::where('ulid', $paymentUlid)->orWhere('gateway_payment_id', $paymentUlid)->first();

            if ($payment && $payment->status !== 'completed') {
                $this->activatePayment($payment, (string) data_get($resource, 'id'), $lifecycle, $activation);
            }
        }

        if (in_array($type, ['BILLING.SUBSCRIPTION.PAYMENT.SUCCEEDED', 'PAYMENT.SALE.COMPLETED'], true)) {
            $subscriptionId = data_get($resource, 'billing_agreement_id') ?: data_get($resource, 'id');
            if ($subscriptionId) {
                $lifecycle->renewFromGatewaySubscription(
                    'paypal',
                    (string) $subscriptionId,
                    (string) data_get($resource, 'id'),
                    (float) data_get($resource, 'amount.total', data_get($resource, 'amount.value', 0)),
                    strtoupper((string) data_get($resource, 'amount.currency', data_get($resource, 'amount.currency_code', 'USD')))
                );
            }
        }

        if (in_array($type, ['BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED'], true)) {
            $subscription = GatewaySubscription::where('gateway', 'paypal')
                ->where('gateway_subscription_id', data_get($resource, 'id'))->first();
            if ($subscription && $subscription->isActive()) {
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

        if ($type === 'PAYMENT.CAPTURE.REFUNDED') {
            $captureId = data_get($resource, 'links.1.href') ? basename((string) data_get($resource, 'links.1.href')) : null;
            $refundReference = (string) data_get($resource, 'id');
            $paymentUlid = data_get($resource, 'custom_id');

            $payment = Payment::query()
                ->when($paymentUlid, fn ($q) => $q->where('ulid', $paymentUlid))
                ->when(! $paymentUlid && $captureId, fn ($q) => $q->where('gateway_payment_id', $captureId))
                ->first();

            if ($payment) {
                $lifecycle->refund($payment, $refundReference);
            }
        }
    }

    private function validPayPalSignature(PaymentGateway $gateway): bool
    {
        $webhookId = $gateway->getCredential('webhook_id');
        $token = $this->payPalAccessToken($gateway);

        if (! $webhookId || ! $token) {
            return false;
        }

        $event = json_decode($this->rawBody, true) ?: $this->payload;

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->payPalBaseUrl($gateway).'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $this->header('paypal-auth-algo'),
                'cert_url' => $this->header('paypal-cert-url'),
                'transmission_id' => $this->header('paypal-transmission-id'),
                'transmission_sig' => $this->header('paypal-transmission-sig'),
                'transmission_time' => $this->header('paypal-transmission-time'),
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    private function payPalAccessToken(PaymentGateway $gateway): ?string
    {
        $clientId = $gateway->getCredential('client_id');
        $clientSecret = $gateway->getCredential('client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->payPalBaseUrl($gateway).'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $response->successful() ? $response->json('access_token') : null;
    }

    private function payPalBaseUrl(PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    // ─── Paddle (Classic API — matches the pay-link checkout flow) ──

    private function processPaddle(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $gateway = $this->gatewayRecord('paddle');

        if (! $gateway) {
            return;
        }

        if (! $this->validPaddleSignature($gateway)) {
            $this->rejectInvalidSignature('paddle');

            return;
        }

        $alert = (string) ($this->payload['alert_name'] ?? '');
        $passthrough = json_decode((string) ($this->payload['passthrough'] ?? ''), true) ?: [];
        $paymentUlid = $passthrough['payment_ulid'] ?? null;
        $orderId = (string) ($this->payload['order_id'] ?? $this->payload['checkout_id'] ?? '');
        $subscriptionId = isset($this->payload['subscription_id']) ? (string) $this->payload['subscription_id'] : null;

        if ($alert === 'payment_succeeded' && $paymentUlid) {
            $payment = Payment::where('ulid', $paymentUlid)->first();

            if ($payment && $payment->status !== 'completed') {
                $this->activatePayment($payment, $orderId, $lifecycle, $activation, $subscriptionId);
            }
        }

        if ($alert === 'subscription_payment_succeeded' && $subscriptionId) {
            // First charge of a new subscription carries the passthrough — activate the
            // pending payment; later charges are renewals of the stored subscription.
            $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

            if ($payment && $payment->status !== 'completed') {
                $this->activatePayment($payment, $orderId, $lifecycle, $activation, $subscriptionId);
            } else {
                $lifecycle->renewFromGatewaySubscription(
                    'paddle',
                    $subscriptionId,
                    $orderId,
                    (float) ($this->payload['sale_gross'] ?? 0),
                    strtoupper((string) ($this->payload['currency'] ?? 'USD'))
                );
            }
        }

        if ($alert === 'subscription_cancelled' && $subscriptionId) {
            $subscription = GatewaySubscription::where('gateway', 'paddle')
                ->where('gateway_subscription_id', $subscriptionId)
                ->first();

            if ($subscription && $subscription->isActive()) {
                $lifecycle->cancelAtPeriodEnd($subscription);
            }
        }

        if (in_array($alert, ['payment_refunded', 'subscription_payment_refunded'], true) && $orderId) {
            $payment = Payment::where('gateway', 'paddle')->where('gateway_payment_id', $orderId)->first();

            if ($payment) {
                $lifecycle->refund($payment, $orderId);
            }
        }
    }

    private function validPaddleSignature(PaymentGateway $gateway): bool
    {
        $publicKey = $gateway->getCredential('public_key');
        $signature = $this->payload['p_signature'] ?? null;

        if (! $publicKey || ! $signature) {
            return false;
        }

        $fields = $this->payload;
        unset($fields['p_signature']);
        ksort($fields);

        foreach ($fields as $key => $value) {
            if (! in_array(gettype($value), ['object', 'array'], true)) {
                $fields[$key] = (string) $value;
            }
        }

        $key = openssl_pkey_get_public($publicKey);

        if ($key === false) {
            Log::warning('Paddle webhook: public key credential is not a valid PEM key.');

            return false;
        }

        return openssl_verify(serialize($fields), (string) base64_decode((string) $signature, true), $key, OPENSSL_ALGO_SHA1) === 1;
    }

    // ─── Razorpay ───────────────────────────────

    private function processRazorpay(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $gateway = $this->gatewayRecord('razorpay');

        if (! $gateway) {
            return;
        }

        if (! $this->validRazorpaySignature($gateway)) {
            $this->rejectInvalidSignature('razorpay');

            return;
        }

        $event = (string) ($this->payload['event'] ?? '');

        if ($event === 'payment_link.paid') {
            $paymentUlid = data_get($this->payload, 'payload.payment_link.entity.reference_id')
                ?: data_get($this->payload, 'payload.payment_link.entity.notes.payment_ulid');
            $razorpayPaymentId = (string) data_get($this->payload, 'payload.payment.entity.id', '');

            $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

            if ($payment && $payment->status !== 'completed') {
                $this->activatePayment($payment, $razorpayPaymentId, $lifecycle, $activation);
            }
        }

        if ($event === 'payment.captured') {
            $entity = data_get($this->payload, 'payload.payment.entity', []);
            $paymentUlid = data_get($entity, 'notes.payment_ulid');

            $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

            if ($payment && $payment->status !== 'completed') {
                $this->activatePayment($payment, (string) data_get($entity, 'id', ''), $lifecycle, $activation);
            }
        }

        if ($event === 'payment.failed') {
            $entity = data_get($this->payload, 'payload.payment.entity', []);
            $paymentUlid = data_get($entity, 'notes.payment_ulid');

            $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

            if ($payment) {
                $lifecycle->fail($payment, (string) data_get($entity, 'error_description', 'payment.failed'));
            }
        }

        if ($event === 'refund.processed') {
            $refund = data_get($this->payload, 'payload.refund.entity', []);
            $razorpayPaymentId = (string) data_get($refund, 'payment_id', '');

            $payment = $razorpayPaymentId
                ? Payment::where('gateway', 'razorpay')->where('gateway_payment_id', $razorpayPaymentId)->first()
                : null;

            if ($payment) {
                $lifecycle->refund($payment, (string) data_get($refund, 'id', ''));
            }
        }
    }

    private function validRazorpaySignature(PaymentGateway $gateway): bool
    {
        $secret = $gateway->getCredential('webhook_secret');
        $signature = $this->header('x-razorpay-signature');

        if (! $secret || ! $signature || $this->rawBody === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $this->rawBody, $secret), $signature);
    }

    // ─── CoinGate ───────────────────────────────

    private function processCoinGate(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $paymentUlid = (string) ($this->payload['order_id'] ?? '');
        $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

        if (! $payment) {
            return;
        }

        // CoinGate has no signature header; we verify the per-order secret token
        // that checkout embedded into the callback URL.
        $storedToken = (string) data_get($payment->metadata, 'coingate_webhook_token', '');
        $providedToken = (string) ($this->payload['token'] ?? '');

        if ($storedToken === '' || $providedToken === '' || ! hash_equals($storedToken, $providedToken)) {
            $this->rejectInvalidSignature('coingate');

            return;
        }

        $status = (string) ($this->payload['status'] ?? '');
        $coingateId = (string) ($this->payload['id'] ?? '');

        if ($status === 'paid' && $payment->status !== 'completed') {
            $this->activatePayment($payment, $coingateId, $lifecycle, $activation);
        }

        if (in_array($status, ['canceled', 'expired', 'invalid'], true)) {
            $lifecycle->fail($payment, 'coingate.'.$status);
        }

        if ($status === 'refunded') {
            $lifecycle->refund($payment, $coingateId);
        }
    }

    // ─── Paystack ───────────────────────────────

    private function processPaystack(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $gateway = $this->gatewayRecord('paystack');

        if (! $gateway) {
            return;
        }

        if (! $this->validPaystackSignature($gateway)) {
            $this->rejectInvalidSignature('paystack');

            return;
        }

        $event = (string) ($this->payload['event'] ?? '');
        $data = $this->payload['data'] ?? [];

        if ($event === 'charge.success') {
            $paymentUlid = (string) data_get($data, 'reference', '');
            $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

            if ($payment && $payment->status !== 'completed') {
                $this->activatePayment($payment, (string) data_get($data, 'id', ''), $lifecycle, $activation);
            }
        }

        if ($event === 'charge.failed') {
            $paymentUlid = (string) data_get($data, 'reference', '');
            $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

            if ($payment) {
                $lifecycle->fail($payment, (string) data_get($data, 'gateway_response', 'charge.failed'));
            }
        }

        if ($event === 'refund.processed') {
            $reference = (string) data_get($data, 'transaction_reference', '');
            $payment = $reference ? Payment::where('ulid', $reference)->first() : null;

            if ($payment) {
                $lifecycle->refund($payment, (string) data_get($data, 'id', ''));
            }
        }
    }

    private function validPaystackSignature(PaymentGateway $gateway): bool
    {
        $secret = $gateway->getCredential('secret_key');
        $signature = $this->header('x-paystack-signature');

        if (! $secret || ! $signature || $this->rawBody === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $this->rawBody, $secret), $signature);
    }

    // ─── 2Checkout (INS notifications) ──────────

    private function processTwoCheckout(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $gateway = $this->gatewayRecord('2checkout');

        if (! $gateway) {
            return;
        }

        if (! $this->validTwoCheckoutHash($gateway)) {
            $this->rejectInvalidSignature('2checkout');

            return;
        }

        $messageType = (string) ($this->payload['message_type'] ?? '');
        $paymentUlid = (string) ($this->payload['vendor_order_id'] ?? '');
        $saleId = (string) ($this->payload['sale_id'] ?? '');
        $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

        if (! $payment) {
            return;
        }

        $invoiceStatus = (string) ($this->payload['invoice_status'] ?? '');

        if (($messageType === 'ORDER_CREATED' || $messageType === 'INVOICE_STATUS_CHANGED')
            && in_array($invoiceStatus, ['approved', 'deposited'], true)
            && $payment->status !== 'completed') {
            $this->activatePayment($payment, $saleId, $lifecycle, $activation);
        }

        if ($messageType === 'FRAUD_STATUS_CHANGED' && ($this->payload['fraud_status'] ?? '') === 'fail') {
            $lifecycle->fail($payment, '2checkout.fraud_review_failed');
        }

        if ($messageType === 'REFUND_ISSUED') {
            $lifecycle->refund($payment, $saleId);
        }
    }

    private function validTwoCheckoutHash(PaymentGateway $gateway): bool
    {
        $secretWord = $gateway->getCredential('secret_key');
        $providedHash = strtoupper((string) ($this->payload['md5_hash'] ?? ''));

        if (! $secretWord || $providedHash === '') {
            return false;
        }

        $expected = strtoupper(md5(
            ((string) ($this->payload['sale_id'] ?? ''))
            .((string) ($this->payload['vendor_id'] ?? ''))
            .((string) ($this->payload['invoice_id'] ?? ''))
            .$secretWord
        ));

        return hash_equals($expected, $providedHash);
    }

    // ─── SSLCommerz ─────────────────────────────

    private function processSslCommerz(SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $gateway = $this->gatewayRecord('sslcommerz');

        if (! $gateway) {
            return;
        }

        $paymentUlid = (string) ($this->payload['tran_id'] ?? '');
        $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

        if (! $payment) {
            return;
        }

        $status = (string) ($this->payload['status'] ?? '');

        if (in_array($status, ['VALID', 'VALIDATED'], true)) {
            if ($this->validSslCommerzPayment($gateway, $payment)) {
                $this->activatePayment($payment, (string) ($this->payload['bank_tran_id'] ?? $this->payload['val_id'] ?? ''), $lifecycle, $activation);
            } else {
                $this->rejectInvalidSignature('sslcommerz');
            }
        } elseif (in_array($status, ['FAILED', 'CANCELLED', 'UNATTEMPTED', 'EXPIRED'], true)) {
            $lifecycle->fail($payment, $status);
        }
    }

    private function validSslCommerzPayment(PaymentGateway $gateway, Payment $payment): bool
    {
        $storeId = $gateway->getCredential('store_id');
        $storePassword = $gateway->getCredential('store_password');
        $valId = $this->payload['val_id'] ?? null;

        if (! $storeId || ! $storePassword || ! $valId) {
            return false;
        }

        $baseUrl = $gateway->is_test_mode ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
        $response = Http::get($baseUrl.'/validator/api/validationserverAPI.php', [
            'val_id' => $valId,
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'format' => 'json',
        ]);

        return $response->successful()
            && in_array($response->json('status'), ['VALID', 'VALIDATED'], true)
            && (string) $response->json('tran_id') === $payment->ulid;
    }
}
