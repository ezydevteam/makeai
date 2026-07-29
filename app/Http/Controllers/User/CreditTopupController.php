<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditTopupController extends Controller
{
    public function index(PaymentGatewayManager $gateways)
    {
        $pricePerUnit = (float) settings('credit_price_per_unit', 0.01);
        $minimum = (float) settings('credit_topup_minimum', 1.00);
        $quickAmounts = settings('credit_topup_quick_amounts', [5, 10, 25, 50, 100]);
        $bonusTiers = settings('credit_topup_bonus_tiers', []);
        $enabled = (bool) settings('credit_topup_enabled', true);

        if (is_string($quickAmounts)) {
            $quickAmounts = json_decode($quickAmounts, true) ?? [];
        }
        if (is_string($bonusTiers)) {
            $bonusTiers = json_decode($bonusTiers, true) ?? [];
        }

        $gatewaysList = $gateways->enabled()->map(function ($gateway) {
            return [
                'id' => $gateway->id,
                'slug' => $gateway->slug,
                'name' => translate($gateway->name),
                'description' => $gateway->description ? translate($gateway->description) : null,
                'is_test_mode' => $gateway->is_test_mode,
                'processing_fee_type' => $gateway->processing_fee_type,
                'processing_fee_value' => $gateway->processing_fee_value,
            ];
        })->values();

        return Inertia::render('User/CreditTopup', [
            'creditTopup' => [
                'enabled' => $enabled,
                'price_per_unit' => $pricePerUnit,
                'minimum' => $minimum,
                'quick_amounts' => $quickAmounts,
                'bonus_tiers' => $bonusTiers,
            ],
            'gateways' => $gatewaysList,
        ]);
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $amount = (float) $validated['amount'];
        $pricePerUnit = (float) settings('credit_price_per_unit', 0.01);
        $bonusTiers = settings('credit_topup_bonus_tiers', []);

        if (is_string($bonusTiers)) {
            $bonusTiers = json_decode($bonusTiers, true) ?? [];
        }

        $result = $this->calculateCredits($amount, $pricePerUnit, $bonusTiers);

        return response()->json([
            'success' => true,
            'amount' => $amount,
            'price_per_unit' => $pricePerUnit,
            'base_credits' => $result['base_credits'],
            'bonus_credits' => $result['bonus_credits'],
            'total_credits' => $result['total_credits'],
            'bonus_percent' => $result['bonus_percent'],
        ]);
    }

    public function checkout(Request $request, PaymentGatewayManager $gateways)
    {
        $enabled = (bool) settings('credit_topup_enabled', true);
        abort_unless($enabled, 404);

        $validated = $request->validate([
            'amount' => 'required|numeric',
            'gateway' => 'required|string',
        ]);

        $minimum = (float) settings('credit_topup_minimum', 1.00);
        $amount = (float) $validated['amount'];

        if ($amount < $minimum) {
            return back()->with('error', translate('Minimum top-up amount is :amount', ['amount' => $minimum]));
        }

        $gateway = PaymentGateway::query()
            ->where('slug', $validated['gateway'])
            ->where('is_enabled', true)
            ->firstOrFail();

        $pricePerUnit = (float) settings('credit_price_per_unit', 0.01);
        $bonusTiers = settings('credit_topup_bonus_tiers', []);

        if (is_string($bonusTiers)) {
            $bonusTiers = json_decode($bonusTiers, true) ?? [];
        }

        $creditCalc = $this->calculateCredits($amount, $pricePerUnit, $bonusTiers);
        $fee = $gateways->processingFee($gateway, $amount);
        $total = $gateways->totalWithFee($gateway, $amount);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'gateway' => $gateway->slug,
            'amount' => $total,
            'currency' => base_currency(),
            'status' => 'pending',
            'type' => 'credit_topup',
            'metadata' => [
                'amount' => $amount,
                'base_credits' => $creditCalc['base_credits'],
                'bonus_credits' => $creditCalc['bonus_credits'],
                'total_credits' => $creditCalc['total_credits'],
                'bonus_percent' => $creditCalc['bonus_percent'],
                'price_per_unit' => $pricePerUnit,
                'processing_fee' => $fee,
                'total_with_fee' => $total,
            ],
        ]);

        // Route to gateway
        return $this->routeToGateway($request, $payment, $gateway, $gateways);
    }

    private function calculateCredits(float $amount, float $pricePerUnit, array $bonusTiers): array
    {
        // Guard a misconfigured price (avoids a DivisionByZeroError 500).
        if ($pricePerUnit <= 0) {
            return ['base_credits' => 0, 'bonus_credits' => 0, 'total_credits' => 0, 'bonus_percent' => 0];
        }

        // round() before floor() — float error otherwise shorts a whole credit on
        // common inputs (e.g. 19.99 / 0.01 = 1998.9999… → floor 1998 instead of 1999).
        $baseCredits = floor(round($amount / $pricePerUnit, 6));
        $bonusPercent = 0;

        // Find matching bonus tier (highest tier where amount >= min_amount)
        foreach ($bonusTiers as $tier) {
            $minAmount = (float) ($tier['min_amount'] ?? 0);
            $tierPercent = (int) ($tier['bonus_percent'] ?? 0);
            if ($amount >= $minAmount && $tierPercent > $bonusPercent) {
                $bonusPercent = $tierPercent;
            }
        }

        $bonusCredits = floor($baseCredits * ($bonusPercent / 100));
        $totalCredits = $baseCredits + $bonusCredits;

        return [
            'base_credits' => (int) $baseCredits,
            'bonus_credits' => (int) $bonusCredits,
            'total_credits' => (int) $totalCredits,
            'bonus_percent' => $bonusPercent,
        ];
    }

    /**
     * Backstop for every gateway's session-creation call.
     *
     * Each `Http::` call below can throw ConnectionException on a timeout, DNS failure or
     * refused outbound connection — `$response->failed()` does not cover that — and an
     * uncaught one is a 500 in the middle of checkout. Catching at the dispatcher covers
     * every gateway at once, including any added later, rather than relying on each
     * builder remembering to guard itself.
     */
    private function routeToGateway(Request $request, Payment $payment, PaymentGateway $gateway, PaymentGatewayManager $gateways)
    {
        try {
            return $this->dispatchToGateway($request, $payment, $gateway, $gateways);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Illuminate\Support\Facades\Log::warning('Top-up gateway session could not be created (connection error)', [
                'gateway' => $gateway->slug,
                'payment_ulid' => $payment->ulid,
                'error' => $e->getMessage(),
            ]);

            return $this->failGatewaySession($payment, translate('Could not reach :gateway. Please try again in a moment.', ['gateway' => $gateway->name]));
        }
    }

    private function dispatchToGateway(Request $request, Payment $payment, PaymentGateway $gateway, PaymentGatewayManager $gateways)
    {
        if ($gateway->slug === 'bank_transfer') {
            return redirect()->route('checkout.bank.show', $payment);
        }

        if ($gateway->slug === 'stripe') {
            return $this->createStripeCheckout($request, $payment);
        }

        if ($gateway->slug === 'paypal') {
            return $this->createPayPalOrder($request, $payment, $gateway);
        }

        if ($gateway->slug === 'paddle') {
            return $this->createPaddlePayLink($request, $payment, $gateway);
        }

        if ($gateway->slug === 'razorpay') {
            return $this->createRazorpayPaymentLink($request, $payment, $gateway);
        }

        if ($gateway->slug === 'sslcommerz') {
            return $this->createSslCommerzSession($request, $payment, $gateway);
        }

        if ($gateway->slug === 'coingate') {
            return $this->createCoinGateOrder($request, $payment, $gateway);
        }

        if ($gateway->slug === 'paystack') {
            return $this->createPaystackTransaction($request, $payment, $gateway);
        }

        if ($gateway->slug === '2checkout') {
            return $this->createTwoCheckoutUrl($request, $payment, $gateway);
        }

        return redirect()->route('checkout.pending', $payment)
            ->with('info', translate(':gateway payment session is pending gateway integration.', ['gateway' => $gateway->name]));
    }

    private function createStripeCheckout(Request $request, Payment $payment)
    {
        $user = $request->user();
        $currency = strtolower($payment->currency ?: 'usd');

        // An ad-hoc amount must be an inline price_data line item. Cashier maps a STRING
        // array key to a Stripe *price id*, so [$currency => $minorAmount] would send
        // `price: "usd"` and Stripe fails with "No such price".
        return $user->checkout(
            [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => ['name' => translate('Credit top-up')],
                    'unit_amount' => $this->minorAmount((float) $payment->amount, $currency),
                ],
                'quantity' => 1,
            ]],
            [
                // Same confirmation screen the other gateways use — which then forwards a
                // top-up buyer to their usage page rather than the dashboard.
                'success_url' => route('checkout.pending', $payment),
                'cancel_url' => route('user.dashboard.credit-topup').'?checkout=cancelled',
                'metadata' => [
                    'payment_id' => $payment->ulid,
                    'type' => 'credit_topup',
                ],
                // Stamp our reference onto the PaymentIntent so it reaches the Charge —
                // a refund webhook carries only the charge, so this is what lets
                // charge.refunded be traced back to this payment.
                'payment_intent_data' => ['metadata' => ['payment_id' => $payment->ulid]],
            ],
        );
    }

    private function createPayPalOrder(Request $request, Payment $payment, PaymentGateway $gateway)
    {
        $token = $this->payPalAccessToken($gateway);
        if (! $token) {
            $payment->update(['status' => 'failed']);
            return back()->with('error', translate('PayPal is not configured.'));
        }

        $baseUrl = $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $response = \Illuminate\Support\Facades\Http::withToken($token)->acceptJson()->post($baseUrl.'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $payment->ulid,
                'custom_id' => $payment->ulid,
                'amount' => [
                    'currency_code' => strtoupper($payment->currency ?? 'USD'),
                    'value' => number_format((float) $payment->amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => route('checkout.paypal.return', $payment),
                'cancel_url' => route('user.dashboard.credit-topup').'?checkout=cancelled',
                'user_action' => 'PAY_NOW',
            ],
        ]);

        if ($response->failed()) {
            $payment->update(['status' => 'failed', 'metadata' => [...($payment->metadata ?: []), 'gateway_error' => $response->json('message', 'PayPal order failed.')]]);
            return back()->with('error', $response->json('message', translate('PayPal order failed.')));
        }

        $approveLink = collect($response->json('links', []))->firstWhere('rel', 'approve');
        $approveUrl = is_array($approveLink) ? ($approveLink['href'] ?? null) : null;
        $payment->update(['gateway_payment_id' => $response->json('id'), 'metadata' => [...($payment->metadata ?: []), 'gateway_order_id' => $response->json('id')]]);

        if (! $approveUrl) {
            return redirect()->route('checkout.pending', $payment)->with('warning', translate('PayPal order was created, but approval URL was not returned.'));
        }

        return redirect()->away($approveUrl);
    }

    private function payPalAccessToken(PaymentGateway $gateway): ?string
    {
        $clientId = $gateway->getCredential('client_id');
        $clientSecret = $gateway->getCredential('client_secret');
        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $baseUrl = $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $response = \Illuminate\Support\Facades\Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($baseUrl.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $response->successful() ? $response->json('access_token') : null;
    }

    /**
     * Create a Paddle transaction and send the buyer to the checkout URL Paddle returns.
     * See CheckoutController::createPaddlePayLink() for the full note.
     */
    private function createPaddlePayLink(Request $request, Payment $payment, PaymentGateway $gateway)
    {
        $apiKey = (string) $gateway->getCredential('api_key');

        if ($apiKey === '') {
            return $this->failGatewaySession($payment, translate('Paddle is not configured.'));
        }

        $base = $gateway->is_test_mode ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

        // A connection failure throws rather than returning a response, and an uncaught
        // one is a 500 mid-checkout — see CheckoutController::gatewayLookup().
        try {
            $response = \Illuminate\Support\Facades\Http::withToken($apiKey)->acceptJson()->timeout(20)->post($base.'/transactions', [
                'items' => [[
                    'quantity' => 1,
                    'price' => [
                        'description' => translate('Credit Top-Up'),
                        'unit_price' => [
                            'amount' => (string) $this->minorAmount((float) $payment->amount, $payment->currency ?? 'USD'),
                            'currency_code' => strtoupper($payment->currency ?? 'USD'),
                        ],
                        'product' => ['name' => translate('Credit Top-Up'), 'tax_category' => 'standard'],
                    ],
                ]],
                'custom_data' => ['payment_ulid' => $payment->ulid],
                'collection_mode' => 'automatic',
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Illuminate\Support\Facades\Log::warning('Paddle top-up transaction could not be created (connection error)', [
                'payment_ulid' => $payment->ulid,
                'error' => $e->getMessage(),
            ]);

            return $this->failGatewaySession($payment, translate('Could not reach Paddle. Please try again in a moment.'));
        }

        if ($response->failed()) {
            return $this->failGatewaySession($payment, (string) (
                $response->json('error.detail')
                ?? $response->json('error.message')
                ?? translate('Paddle checkout could not be started.')
            ));
        }

        $checkoutUrl = (string) $response->json('data.checkout.url');

        // Only populated from the seller's default payment link (Paddle dashboard →
        // Checkout → Checkout settings); without it there is nowhere to send the buyer.
        if ($checkoutUrl === '') {
            return $this->failGatewaySession($payment, translate('Paddle has no default payment link configured. Set one in the Paddle dashboard under Checkout → Checkout settings.'));
        }

        $payment->update([
            'gateway_payment_id' => $response->json('data.id'),
            'metadata' => [...($payment->metadata ?: []), 'gateway_pay_link' => $checkoutUrl],
        ]);

        return redirect()->away($checkoutUrl);
    }

    private function createRazorpayPaymentLink(Request $request, Payment $payment, PaymentGateway $gateway)
    {
        $keyId = $gateway->getCredential('key_id');
        $keySecret = $gateway->getCredential('key_secret');
        if (! $keyId || ! $keySecret) {
            return $this->failGatewaySession($payment, 'Razorpay is not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::withBasicAuth($keyId, $keySecret)->acceptJson()->post('https://api.razorpay.com/v1/payment_links', [
            'amount' => $this->minorAmount((float) $payment->amount, $payment->currency ?? 'USD'),
            'currency' => strtoupper($payment->currency ?? 'USD'),
            'description' => translate('Credit Top-Up'),
            'reference_id' => $payment->ulid,
            // Server-side confirmation on return, so a top-up is not stranded when the
            // webhook never lands. That handler branches on type and credits the wallet.
            'callback_url' => route('checkout.razorpay.return', $payment),
            'callback_method' => 'get',
            'customer' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'notes' => ['payment_ulid' => $payment->ulid],
        ]);

        if ($response->failed()) {
            return $this->failGatewaySession($payment, $response->json('error.description', 'Razorpay payment link failed.'));
        }

        $payment->update([
            'gateway_payment_id' => $response->json('id'),
            'metadata' => [...($payment->metadata ?: []), 'gateway_payment_link' => $response->json('short_url')],
        ]);

        return redirect()->away($response->json('short_url'));
    }

    private function createSslCommerzSession(Request $request, Payment $payment, PaymentGateway $gateway)
    {
        $storeId = $gateway->getCredential('store_id');
        $storePassword = $gateway->getCredential('store_password');
        if (! $storeId || ! $storePassword) {
            return $this->failGatewaySession($payment, translate('SSLCommerz is not configured.'));
        }

        $baseUrl = $gateway->is_test_mode ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
        $response = \Illuminate\Support\Facades\Http::asForm()->post($baseUrl.'/gwprocess/v4/api.php', [
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => strtoupper($payment->currency ?? 'USD'),
            'tran_id' => $payment->ulid,
            // SSLCommerz returns the buyer by POSTing a form, so this must be the
            // POST-capable verifying route — the GET-only pending page 405s every time.
            'success_url' => route('checkout.sslcommerz.return', $payment),
            'fail_url' => route('checkout.sslcommerz.return', $payment),
            'cancel_url' => route('user.dashboard.credit-topup').'?checkout=cancelled',
            'ipn_url' => route('webhooks.sslcommerz'),
            'cus_name' => $request->user()->name,
            'cus_email' => $request->user()->email,
            'cus_add1' => 'N/A',
            'cus_city' => 'N/A',
            'cus_country' => 'N/A',
            'cus_phone' => 'N/A',
            'shipping_method' => 'NO',
            'product_name' => translate('Credit Top-Up'),
            'product_category' => 'Digital Goods',
            'product_profile' => 'non-physical-goods',
        ]);

        if ($response->failed() || ! $response->json('GatewayPageURL')) {
            return $this->failGatewaySession($payment, $response->json('failedreason', 'SSLCommerz session failed.'));
        }

        $payment->update([
            'gateway_payment_id' => $response->json('sessionkey'),
            'metadata' => [...($payment->metadata ?: []), 'gateway_session_id' => $response->json('sessionkey')],
        ]);

        return redirect()->away($response->json('GatewayPageURL'));
    }

    private function createCoinGateOrder(Request $request, Payment $payment, PaymentGateway $gateway)
    {
        $token = $gateway->getCredential('auth_token');
        if (! $token) {
            return $this->failGatewaySession($payment, 'CoinGate is not configured.');
        }

        $baseUrl = $gateway->is_test_mode ? 'https://api-sandbox.coingate.com/v2' : 'https://api.coingate.com/v2';
        $webhookToken = bin2hex(random_bytes(16));
        $response = \Illuminate\Support\Facades\Http::withToken($token)->acceptJson()->asForm()->post($baseUrl.'/orders', [
            'order_id' => $payment->ulid,
            'price_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'price_currency' => strtoupper($payment->currency ?? 'USD'),
            'receive_currency' => strtoupper($payment->currency ?? 'USD'),
            'title' => translate('Credit Top-Up'),
            'callback_url' => route('webhooks.coingate').'?token='.$webhookToken,
            'cancel_url' => route('user.dashboard.credit-topup').'?checkout=cancelled',
            // Confirms against CoinGate's order API instead of trusting the redirect.
            'success_url' => route('checkout.coingate.return', $payment),
        ]);

        if ($response->failed()) {
            return $this->failGatewaySession($payment, $response->json('message', 'CoinGate order failed.'));
        }

        $payment->update([
            'gateway_payment_id' => $response->json('id'),
            'metadata' => [...($payment->metadata ?: []), 'coingate_webhook_token' => $webhookToken],
        ]);

        return redirect()->away($response->json('payment_url'));
    }

    private function createPaystackTransaction(Request $request, Payment $payment, PaymentGateway $gateway)
    {
        $secretKey = $gateway->getCredential('secret_key');
        if (! $secretKey) {
            return $this->failGatewaySession($payment, 'Paystack is not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::withToken($secretKey)->acceptJson()->post('https://api.paystack.co/transaction/initialize', [
            'email' => $request->user()->email,
            'amount' => $this->minorAmount((float) $payment->amount, $payment->currency ?? 'USD'),
            'currency' => strtoupper($payment->currency ?? 'USD'),
            'reference' => $payment->ulid,
            // Confirms via Paystack's verify endpoint instead of trusting the redirect.
            'callback_url' => route('checkout.paystack.return', $payment),
            'metadata' => ['payment_ulid' => $payment->ulid],
        ]);

        if ($response->failed() || ! $response->json('status')) {
            return $this->failGatewaySession($payment, $response->json('message', 'Paystack transaction failed.'));
        }

        $payment->update([
            'gateway_payment_id' => $response->json('data.reference'),
            'metadata' => [...($payment->metadata ?: []), 'gateway_access_code' => $response->json('data.access_code')],
        ]);

        return redirect()->away($response->json('data.authorization_url'));
    }

    /**
     * 2Checkout ConvertPlus buy link. A dynamic (ad-hoc price) link must be signed or
     * 2Checkout rejects it and sends the buyer to its documentation site — see
     * CheckoutController::createTwoCheckoutUrl() for the full note.
     */
    private function createTwoCheckoutUrl(Request $request, Payment $payment, PaymentGateway $gateway)
    {
        $merchantCode = $gateway->getCredential('merchant_code');
        $secretWord = $gateway->getCredential('secret_key');

        if (! $merchantCode || ! $secretWord) {
            return $this->failGatewaySession($payment, translate('2Checkout is not configured.'));
        }

        // The general signed set (return-url, return-type, order-ext-ref) plus the
        // dynamic-product set. `order-ext-ref` comes back as INS `vendor_order_id`, which
        // is how the webhook finds this payment.
        $signed = [
            'currency' => strtoupper($payment->currency ?? 'USD'),
            'order-ext-ref' => $payment->ulid,
            'price' => number_format((float) $payment->amount, 2, '.', ''),
            'prod' => translate('Credit Top-Up'),
            'qty' => '1',
            'return-type' => 'redirect',
            'return-url' => route('checkout.pending', $payment),
            'type' => 'digital',
        ];

        ksort($signed);
        $payload = collect($signed)->map(fn ($value) => strlen((string) $value).(string) $value)->implode('');

        $query = [
            ...$signed,
            'merchant' => $merchantCode,
            'dynamic' => '1',
            'signature' => hash_hmac('sha256', $payload, $secretWord),
        ];

        if ($gateway->is_test_mode) {
            $query['test'] = '1';
        }

        $payment->update(['gateway_payment_id' => $payment->ulid]);

        return redirect()->away('https://secure.2checkout.com/checkout/buy/?'.http_build_query($query));
    }

    private function failGatewaySession(Payment $payment, string $message)
    {
        $payment->update([
            'status' => 'failed',
            'metadata' => [...($payment->metadata ?: []), 'gateway_error' => $message],
        ]);

        return back()->with('error', translate($message));
    }

    private function minorAmount(float $amount, string $currency): int
    {
        $zeroDecimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'];
        return in_array(strtoupper($currency), $zeroDecimal, true) ? (int) round($amount) : (int) round($amount * 100);
    }
}
