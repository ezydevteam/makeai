<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentWebhookController extends Controller
{
    public function paypal(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $gateway = PaymentGateway::where('slug', 'paypal')->where('is_enabled', true)->firstOrFail();

        if (! $this->validPayPalSignature($request, $gateway)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->all();
        $type = $event['event_type'] ?? '';
        $resource = $event['resource'] ?? [];

        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            $paymentUlid = data_get($resource, 'custom_id') ?: data_get($resource, 'supplementary_data.related_ids.order_id');
            $payment = Payment::where('ulid', $paymentUlid)
                ->orWhere('gateway_payment_id', $paymentUlid)
                ->first();

            if ($payment) {
                $lifecycle->activateFromPayment($payment, data_get($resource, 'id'));
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
            $subscription = GatewaySubscription::where('gateway', 'paypal')
                ->where('gateway_subscription_id', data_get($resource, 'id'))
                ->first();

            if ($subscription) {
                $lifecycle->cancelAtPeriodEnd($subscription);
            }
        }

        if (in_array($type, ['PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED', 'CHECKOUT.ORDER.VOIDED'], true)) {
            $paymentUlid = data_get($resource, 'custom_id') ?: data_get($resource, 'supplementary_data.related_ids.order_id');
            $payment = Payment::where('ulid', $paymentUlid)
                ->orWhere('gateway_payment_id', $paymentUlid)
                ->first();

            if ($payment) {
                $lifecycle->fail($payment, $type);
            }
        }

        return response()->json(['received' => true]);
    }

    public function paddle(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $gateway = PaymentGateway::where('slug', 'paddle')->where('is_enabled', true)->firstOrFail();

        if (! $this->validPaddleSignature($request, $gateway)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $passthrough = json_decode((string) $request->input('passthrough', '{}'), true) ?: [];
        $paymentUlid = $passthrough['payment_ulid'] ?? $request->input('payment_ulid') ?? $request->input('order_id');
        $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

        if ($payment && in_array($request->input('alert_name'), ['payment_succeeded', 'subscription_payment_succeeded'], true)) {
            $lifecycle->activateFromPayment($payment, $request->input('checkout_id') ?: $request->input('order_id'));
        }

        if ($payment && in_array($request->input('alert_name'), ['payment_refunded', 'subscription_payment_failed'], true)) {
            $lifecycle->fail($payment, (string) $request->input('alert_name'));
        }

        return response()->json(['received' => true]);
    }

    public function razorpay(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $gateway = PaymentGateway::where('slug', 'razorpay')->where('is_enabled', true)->firstOrFail();
        $secret = $gateway->getCredential('webhook_secret');

        if (! $secret || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), (string) $request->header('X-Razorpay-Signature'))) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $entity = $request->input('payload.payment_link.entity') ?: $request->input('payload.payment.entity', []);
        $paymentUlid = data_get($entity, 'reference_id') ?: data_get($entity, 'notes.payment_ulid');
        $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

        if ($payment && in_array($event, ['payment_link.paid', 'payment.captured'], true)) {
            $lifecycle->activateFromPayment($payment, data_get($entity, 'payment_id', data_get($entity, 'id')));
        }

        if ($payment && in_array($event, ['payment.failed', 'payment_link.cancelled'], true)) {
            $lifecycle->fail($payment, (string) $event);
        }

        return response()->json(['received' => true]);
    }

    public function sslcommerz(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $gateway = PaymentGateway::where('slug', 'sslcommerz')->where('is_enabled', true)->firstOrFail();
        $payment = Payment::where('ulid', $request->input('tran_id'))->first();

        if (! $payment) {
            return response()->json(['received' => true]);
        }

        if ($request->input('status') === 'VALID' || $request->input('status') === 'VALIDATED') {
            if ($this->validSslCommerzPayment($request, $gateway, $payment)) {
                $lifecycle->activateFromPayment($payment, (string) $request->input('bank_tran_id', $request->input('val_id')));
            }
        } elseif (in_array($request->input('status'), ['FAILED', 'CANCELLED', 'UNATTEMPTED', 'EXPIRED'], true)) {
            $lifecycle->fail($payment, (string) $request->input('status'));
        }

        return response()->json(['received' => true]);
    }

    public function coingate(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $payment = Payment::where('ulid', $request->input('order_id'))->first();

        if (! $payment || $request->query('token') !== data_get($payment->metadata, 'coingate_webhook_token')) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        if ($request->input('status') === 'paid') {
            $lifecycle->activateFromPayment($payment, (string) $request->input('id'));
        }

        if (in_array($request->input('status'), ['invalid', 'expired', 'canceled', 'refunded'], true)) {
            $lifecycle->fail($payment, (string) $request->input('status'));
        }

        return response()->json(['received' => true]);
    }

    public function paystack(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $gateway = PaymentGateway::where('slug', 'paystack')->where('is_enabled', true)->firstOrFail();
        $secret = $gateway->getCredential('secret_key');

        if (! $secret || ! hash_equals(hash_hmac('sha512', $request->getContent(), $secret), (string) $request->header('X-Paystack-Signature'))) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $reference = $request->input('data.reference');
        $payment = $reference ? Payment::where('ulid', $reference)->orWhere('gateway_payment_id', $reference)->first() : null;

        if ($payment && $request->input('event') === 'charge.success') {
            $lifecycle->activateFromPayment($payment, (string) $request->input('data.id', $reference));
        }

        if ($payment && in_array($request->input('event'), ['charge.failed', 'transfer.failed'], true)) {
            $lifecycle->fail($payment, (string) $request->input('event'));
        }

        return response()->json(['received' => true]);
    }

    public function twoCheckout(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $gateway = PaymentGateway::where('slug', '2checkout')->where('is_enabled', true)->firstOrFail();

        if (! $this->validTwoCheckoutHash($request, $gateway)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $paymentUlid = $request->input('vendor_order_id') ?: $request->input('merchant_order_id');
        $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;
        $messageType = $request->input('message_type');

        if ($payment && in_array($messageType, ['ORDER_CREATED', 'COMPLETE', 'FRAUD_STATUS_CHANGED'], true)) {
            $lifecycle->activateFromPayment($payment, (string) $request->input('sale_id', $request->input('invoice_id')));
        }

        if ($payment && in_array($messageType, ['REFUND_ISSUED', 'RECURRING_STOPPED'], true)) {
            $lifecycle->fail($payment, (string) $messageType);
        }

        return response()->json(['received' => true]);
    }

    // ─── Signature Verification ───────────────

    private function validPayPalSignature(Request $request, PaymentGateway $gateway): bool
    {
        $webhookId = $gateway->getCredential('webhook_id');
        $token = $this->payPalAccessToken($gateway);

        if (! $webhookId || ! $token) {
            return false;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->payPalBaseUrl($gateway).'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
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
            ->post($this->payPalBaseUrl($gateway).'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    private function payPalBaseUrl(PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private function validPaddleSignature(Request $request, PaymentGateway $gateway): bool
    {
        $publicKey = trim((string) $gateway->getCredential('public_key'));
        $signature = base64_decode((string) $request->input('p_signature'), true);

        if (! $publicKey || ! $signature) {
            return false;
        }

        $fields = $request->except('p_signature');
        ksort($fields);
        $serialized = serialize($fields);
        $key = openssl_pkey_get_public($publicKey);

        return $key !== false && openssl_verify($serialized, $signature, $key, OPENSSL_ALGO_SHA1) === 1;
    }

    private function validSslCommerzPayment(Request $request, PaymentGateway $gateway, Payment $payment): bool
    {
        $storeId = $gateway->getCredential('store_id');
        $storePassword = $gateway->getCredential('store_password');
        $valId = $request->input('val_id');

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

    private function validTwoCheckoutHash(Request $request, PaymentGateway $gateway): bool
    {
        $secret = $gateway->getCredential('secret_key');
        $merchantCode = $gateway->getCredential('merchant_code');
        $received = strtoupper((string) $request->input('md5_hash'));

        if (! $secret || ! $merchantCode || ! $received) {
            return false;
        }

        $expected = strtoupper(md5(
            (string) $request->input('sale_id').
            $merchantCode.
            (string) $request->input('invoice_id').
            $secret
        ));

        return hash_equals($expected, $received);
    }
}
