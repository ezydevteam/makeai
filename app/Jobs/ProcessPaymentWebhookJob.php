<?php

namespace App\Jobs;

use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\NotificationEventService;
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

        // The gateway charged the customer but we never completed the activation. Logging
        // alone meant nobody found out until they complained — tell an admin so the payment
        // can be activated by hand.
        try {
            app(NotificationEventService::class)->webhookProcessingFailed(
                $this->gateway,
                $e->getMessage(),
                $this->referencedPaymentUlid(),
            );
        } catch (\Throwable $notifyError) {
            Log::error('Could not notify admins of the failed payment webhook', [
                'gateway' => $this->gateway,
                'error' => $notifyError->getMessage(),
            ]);
        }
    }

    /**
     * Best-effort dig for the payment reference each gateway puts in its payload, so the
     * admin alert can name the transaction.
     */
    private function referencedPaymentUlid(): ?string
    {
        $reference = data_get($this->payload, 'resource.custom_id')                      // PayPal
            ?: data_get($this->payload, 'data.reference')                                // Paystack
            ?: data_get($this->payload, 'payload.payment_link.entity.reference_id')      // Razorpay
            ?: data_get($this->payload, 'vendor_order_id')                               // 2Checkout
            ?: data_get($this->payload, 'order_id')                                      // CoinGate
            ?: data_get($this->payload, 'tran_id')                                       // SSLCommerz
            ?: data_get(json_decode((string) data_get($this->payload, 'passthrough', ''), true) ?: [], 'payment_ulid'); // Paddle

        return filled($reference) ? (string) $reference : null;
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
     * Assert the gateway-reported paid amount covers what we expected to charge.
     *
     * Signatures prove the message is authentic but do not bind the amount — on
     * gateways where the buyer can influence the price (2Checkout dynamic buy-link)
     * or under/overpay (crypto), a valid-but-underpaid notification must not
     * activate a plan. Overpayment is allowed; a 1-cent tolerance absorbs rounding.
     *
     * A zero/absent reported amount used to be waved through as "unknown", which quietly
     * defeated the entire check: a payload that simply omitted the amount field bypassed
     * it. It is now a rejection, since this guard is only ever consulted for a charge we
     * expect real money for. A genuinely free payment never reaches here — a zero-amount
     * checkout is activated directly and never goes near a gateway.
     */
    private function paidAmountCovers(Payment $payment, float $paidAmount, ?string $paidCurrency = null): bool
    {
        $expected = (float) $payment->amount;

        // Nothing was expected (a fully-discounted order) — nothing to verify.
        if ($expected <= 0.0) {
            return true;
        }

        if ($paidAmount <= 0.0) {
            Log::warning('Webhook rejected: no paid amount reported for a charge we expected money for', [
                'gateway' => $this->gateway,
                'payment_ulid' => $payment->ulid,
                'expected_amount' => $expected,
            ]);

            return false;
        }

        if ($paidCurrency !== null && strtoupper($paidCurrency) !== strtoupper((string) $payment->currency)) {
            Log::warning('Webhook rejected: paid currency mismatch', [
                'gateway' => $this->gateway,
                'payment_ulid' => $payment->ulid,
                'expected_currency' => $payment->currency,
                'paid_currency' => $paidCurrency,
            ]);

            return false;
        }

        if (($paidAmount + 0.01) < (float) $payment->amount) {
            Log::warning('Webhook rejected: underpaid amount', [
                'gateway' => $this->gateway,
                'payment_ulid' => $payment->ulid,
                'expected_amount' => (float) $payment->amount,
                'paid_amount' => $paidAmount,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Minor units to major, for the gateways that report totals in the smallest unit.
     *
     * NOT a plain `/ 100`: a zero-decimal currency has no minor unit, so ¥5000 is 5000 and
     * dividing it would read a correctly-paid order as a hundredth of its price and reject
     * it as underpaid. Mirrors CheckoutController::minorAmount()/majorAmount().
     */
    private function majorAmount(int $minor, string $currency): float
    {
        $zeroDecimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'];

        return in_array(strtoupper($currency), $zeroDecimal, true) ? (float) $minor : $minor / 100;
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
            // Only resolve when we have a real reference — a null/empty ulid would turn
            // the query into `WHERE ulid IS NULL OR gateway_payment_id IS NULL` and match
            // an unrelated pending payment.
            $payment = filled($paymentUlid)
                ? Payment::where('ulid', $paymentUlid)->orWhere('gateway_payment_id', $paymentUlid)->first()
                : null;

            if ($payment && $payment->status !== 'completed') {
                $this->activatePayment($payment, (string) data_get($resource, 'id'), $lifecycle, $activation);
            }
        }

        // Subscription approved & activated — the reliable server-side activation
        // of the pending checkout payment (independent of the browser return).
        if ($type === 'BILLING.SUBSCRIPTION.ACTIVATED') {
            $subscriptionId = (string) data_get($resource, 'id');
            $ulid = data_get($resource, 'custom_id');
            $payment = $ulid ? Payment::where('ulid', $ulid)->first() : null;

            if ($payment && $payment->status !== 'completed' && $subscriptionId) {
                $this->activatePayment($payment, $subscriptionId, $lifecycle, $activation, $subscriptionId);
            }
        }

        if (in_array($type, ['BILLING.SUBSCRIPTION.PAYMENT.SUCCEEDED', 'PAYMENT.SALE.COMPLETED'], true)) {
            $subscriptionId = data_get($resource, 'billing_agreement_id') ?: data_get($resource, 'id');
            if ($subscriptionId) {
                // The first charge of a new subscription activates the pending
                // checkout payment; every later charge is a renewal.
                $pending = Payment::where('gateway', 'paypal')
                    ->where('metadata->paypal_subscription_id', (string) $subscriptionId)
                    ->where('status', 'pending')
                    ->first();

                if ($pending) {
                    $this->activatePayment($pending, (string) $subscriptionId, $lifecycle, $activation, (string) $subscriptionId);
                } else {
                    $lifecycle->renewFromGatewaySubscription(
                        'paypal',
                        (string) $subscriptionId,
                        (string) data_get($resource, 'id'),
                        (float) data_get($resource, 'amount.total', data_get($resource, 'amount.value', 0)),
                        strtoupper((string) data_get($resource, 'amount.currency', data_get($resource, 'amount.currency_code', 'USD')))
                    );
                }
            }
        }

        // Plan change approved on PayPal (e.g. an in-place upgrade the buyer
        // approved) — sync the local plan to the new PayPal plan id.
        if ($type === 'BILLING.SUBSCRIPTION.UPDATED') {
            $this->syncPayPalPlanChange($resource);
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
            $payment = filled($paymentUlid)
                ? Payment::where('ulid', $paymentUlid)->orWhere('gateway_payment_id', $paymentUlid)->first()
                : null;
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

    /**
     * Reconcile a PayPal subscription's plan with the local plan after a revise
     * takes effect (matches the new PayPal plan id back to a local Plan).
     */
    private function syncPayPalPlanChange(array $resource): void
    {
        $subscriptionId = (string) data_get($resource, 'id');
        $newPayPalPlanId = (string) data_get($resource, 'plan_id');

        if (! $subscriptionId || ! $newPayPalPlanId) {
            return;
        }

        $subscription = GatewaySubscription::where('gateway', 'paypal')
            ->where('gateway_subscription_id', $subscriptionId)
            ->first();

        if (! $subscription) {
            return;
        }

        $target = Plan::where('paypal_plan_monthly_id', $newPayPalPlanId)
            ->orWhere('paypal_plan_yearly_id', $newPayPalPlanId)
            ->first();

        if (! $target || $target->id === $subscription->plan_id) {
            return;
        }

        $oldPlan = $subscription->plan;
        $cycle = $target->paypal_plan_yearly_id === $newPayPalPlanId ? 'yearly' : 'monthly';

        $subscription->update([
            'plan_id' => $target->id,
            'billing_cycle' => $cycle,
            'scheduled_plan_id' => null,
            'scheduled_billing_cycle' => null,
            'scheduled_change_at' => null,
        ]);

        $subscription->user()->update(['plan_id' => $target->id]);

        app(NotificationEventService::class)->planChanged($subscription->user, $oldPlan, $target, $subscription);
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

    // ─── Paddle ─────────────────────────────────

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

        $event = (string) ($this->payload['event_type'] ?? '');
        $data = $this->payload['data'] ?? [];
        $paymentUlid = data_get($data, 'custom_data.payment_ulid');
        $transactionId = (string) data_get($data, 'id', '');
        $subscriptionId = data_get($data, 'subscription_id') ? (string) data_get($data, 'subscription_id') : null;

        // `transaction.completed` is the settled event — `transaction.paid` can still be
        // followed by a failure on some payment methods.
        if ($event === 'transaction.completed') {
            $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

            if ($payment && $payment->status !== 'completed') {
                // Totals are minor-unit strings; the guard compares major units.
                $currency = (string) data_get($data, 'currency_code', $payment->currency);
                $paid = $this->majorAmount((int) data_get($data, 'details.totals.total', 0), $currency);

                if (! $this->paidAmountCovers($payment, $paid, $currency)) {
                    return;
                }

                $this->activatePayment($payment, $transactionId, $lifecycle, $activation, $subscriptionId);

                return;
            }

            // No pending payment of ours — a renewal of a subscription we already track.
            if (! $payment && $subscriptionId) {
                $currency = strtoupper((string) data_get($data, 'currency_code', 'USD'));

                $lifecycle->renewFromGatewaySubscription(
                    'paddle',
                    $subscriptionId,
                    $transactionId,
                    $this->majorAmount((int) data_get($data, 'details.totals.total', 0), $currency),
                    $currency
                );
            }
        }

        if ($event === 'transaction.payment_failed') {
            $payment = $paymentUlid ? Payment::where('ulid', $paymentUlid)->first() : null;

            if ($payment) {
                $lifecycle->fail($payment, 'transaction.payment_failed');
            }
        }

        if ($event === 'subscription.canceled' && $subscriptionId) {
            $subscription = GatewaySubscription::where('gateway', 'paddle')
                ->where('gateway_subscription_id', $subscriptionId)
                ->first();

            if ($subscription && $subscription->isActive()) {
                $lifecycle->cancelAtPeriodEnd($subscription);
            }
        }

        // Refunds are adjustments on Billing, not a refund event on the transaction.
        if (in_array($event, ['adjustment.created', 'adjustment.updated'], true)
            && data_get($data, 'action') === 'refund'
            && data_get($data, 'status') === 'approved') {
            $adjustedTransaction = (string) data_get($data, 'transaction_id', '');

            $payment = $adjustedTransaction
                ? Payment::where('gateway', 'paddle')->where('gateway_payment_id', $adjustedTransaction)->first()
                : null;

            if ($payment) {
                $lifecycle->refund($payment, (string) data_get($data, 'id', ''));
            }
        }
    }

    /**
     * `Paddle-Signature: ts=<unix>;h1=<hmac>` where the HMAC is SHA-256 over
     * "<ts>:<raw body>" keyed with the notification destination's secret (`ntfset_01…`).
     * The raw body must be byte-for-byte what Paddle sent, which is why the controller
     * captures it before Laravel parses the request.
     */
    private function validPaddleSignature(PaymentGateway $gateway): bool
    {
        $secret = $gateway->getCredential('webhook_secret');
        $header = $this->header('paddle-signature');

        if (! $secret || ! $header || $this->rawBody === '') {
            return false;
        }

        $parts = [];

        foreach (explode(';', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, null);
            $parts[trim((string) $key)] = $value;
        }

        $timestamp = $parts['ts'] ?? null;
        $signature = $parts['h1'] ?? null;

        if (! $timestamp || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.':'.$this->rawBody, $secret), $signature);
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
            // Crypto orders can settle under/over the requested amount — confirm the
            // priced total in our order currency covers what we expected to charge.
            $paidAmount = (float) ($this->payload['price_amount'] ?? 0);
            $paidCurrency = (string) ($this->payload['price_currency'] ?? $payment->currency);

            if (! $this->paidAmountCovers($payment, $paidAmount, $paidCurrency)) {
                return;
            }

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
                // Paystack reports the charge in minor units. The earlier audit skipped
                // this guard here on the grounds that the amount is fixed server-side at
                // initialize — but the confirm-on-return path checks it, and one of the
                // two silently disagreeing about what counts as paid is worse than either
                // rule on its own. The signature proves the message is authentic; it does
                // not prove the figure inside it matches what we billed.
                $currency = (string) data_get($data, 'currency', $payment->currency);
                $paid = $this->majorAmount((int) data_get($data, 'amount', 0), $currency);

                if (! $this->paidAmountCovers($payment, $paid, $currency)) {
                    return;
                }

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
            // The INS hash (sale_id+vendor_id+invoice_id+secret) does NOT bind the
            // amount, and the dynamic buy-link carries a buyer-editable price — so we
            // must confirm the invoiced total matches what we expected to charge.
            $paidAmount = (float) ($this->payload['invoice_list_amount']
                ?? $this->payload['item_list_amount']
                ?? $this->payload['total'] ?? 0);
            $paidCurrency = (string) ($this->payload['list_currency'] ?? $this->payload['payout_currency'] ?? $payment->currency);

            if (! $this->paidAmountCovers($payment, $paidAmount, $paidCurrency)) {
                return;
            }

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

        if (! $response->successful()
            || ! in_array($response->json('status'), ['VALID', 'VALIDATED'], true)
            || (string) $response->json('tran_id') !== $payment->ulid) {
            return false;
        }

        // Confirm the settled amount covers the expected charge. Prefer the
        // transaction-currency fields (currency_amount/currency_type), which echo
        // what we requested; `amount`/`currency` may be the post-conversion store
        // currency on a multi-currency store, so only fall back to them.
        $paidAmount = (float) ($response->json('currency_amount') ?: $response->json('amount'));
        $paidCurrency = (string) ($response->json('currency_type') ?: $response->json('currency') ?: $payment->currency);

        return $this->paidAmountCovers($payment, $paidAmount, $paidCurrency);
    }
}
