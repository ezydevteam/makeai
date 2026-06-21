<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankPaymentProofRequest;
use App\Http\Requests\CheckoutSessionRequest;
use App\Models\Coupon;
use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\NotificationEventService;
use App\Services\Payment\PaymentActivationService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Pricing\PlanPriceResolver;
use App\Support\CountryCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function show(Request $request, PlanPriceResolver $resolver, PaymentGatewayManager $gateways): Response
    {

        $validated = $request->validate([
            'plan' => ['required', 'string', 'max:120'],
            'billing' => ['required', 'string', 'in:monthly,yearly,lifetime'],
        ]);

        $countryCode = $request->attributes->get('pricing_country', session('pricing_country'));
        $plan = Plan::active()
            ->with('countryPrices')
            ->where('slug', $validated['plan'])
            ->firstOrFail();

        $pricing = $resolver->resolve($plan, $countryCode);
        $cycle = $pricing[$validated['billing']];
        $amount = (float) $cycle['amount'];
        $currency = $pricing['currency_code'];

        return Inertia::render('Checkout', [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'credits' => $plan->credits,
                'features' => $plan->features ?: [],
            ],
            'billing' => $validated['billing'],
            'pricing' => [
                'country_code' => $pricing['country_code'],
                'country_name' => $pricing['country_name'],
                'currency_code' => $currency,
                'source' => $pricing['source'],
                'cycle' => $cycle,
            ],
            'gateways' => $gateways->enabled()->map(fn ($gateway) => [
                'id' => $gateway->id,
                'slug' => $gateway->slug,
                'name' => translate($gateway->name),
                'description' => $gateway->description ? translate($gateway->description) : null,
                'is_test_mode' => $gateway->is_test_mode,
                'processing_fee_type' => $gateway->processing_fee_type,
                'processing_fee_value' => $gateway->processing_fee_value,
                'processing_fee_currency' => $gateway->processing_fee_currency,
                'fee_amount' => $gateways->processingFee($gateway, $amount),
                'total_amount' => $gateways->totalWithFee($gateway, $amount),
                'fee_formatted' => CountryCatalog::formatMoney($gateways->processingFee($gateway, $amount), $currency),
                'total_formatted' => CountryCatalog::formatMoney($gateways->totalWithFee($gateway, $amount), $currency),
            ])->values(),
        ]);
    }

    public function createSession(CheckoutSessionRequest $request, PlanPriceResolver $resolver, PaymentGatewayManager $gateways): RedirectResponse
    {

        $data = $request->validated();
        $gateway = PaymentGateway::query()
            ->where('slug', $data['gateway'])
            ->where('is_enabled', true)
            ->firstOrFail();

        $plan = Plan::active()
            ->with('countryPrices')
            ->where('slug', $data['plan'])
            ->firstOrFail();

        $countryCode = $request->attributes->get('pricing_country', session('pricing_country'));
        $pricing = $resolver->resolve($plan, $countryCode);
        $cycle = $pricing[$data['billing']];
        $coupon = $this->validCoupon($data['coupon'] ?? null, $plan, $request->user());
        $cycle = $this->discountedCycle($cycle, $coupon);
        $amount = (float) $cycle['amount'];
        $fee = $gateways->processingFee($gateway, $amount);
        $total = $gateways->totalWithFee($gateway, $amount);

        if ($cycle['is_trial'] && $amount <= 0) {
            $subscription = $this->createTrialSubscription($request, $plan, $data['billing'], $gateway, $cycle, $pricing);

            Payment::create([
                'user_id' => $request->user()->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'gateway' => $gateway->slug,
                'gateway_payment_id' => 'trial-'.$subscription->id,
                'amount' => 0,
                'currency' => $pricing['currency_code'],
                'status' => 'completed',
                'type' => 'subscription',
                'metadata' => $this->paymentMetadata($data['billing'], $pricing, $cycle, $gateway, $fee, $total, $coupon),
            ]);

            return redirect()->route('user.dashboard')->with('success', translate('Trial activated successfully.'));
        }

        // Ensure payment_gateways table exists by creating payment with zero gateway metadata via DB
        // (payment_gateways is created by an earlier migration)
        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'plan_id' => $plan->id,
            'gateway' => $gateway->slug,
            'amount' => $total,
            'currency' => $pricing['currency_code'],
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => $this->paymentMetadata($data['billing'], $pricing, $cycle, $gateway, $fee, $total, $coupon),
        ]);

        if ($gateway->slug === 'bank_transfer') {
            app(NotificationEventService::class)->transactionPending($payment);

            return redirect()->route('checkout.bank.show', $payment);
        }

        if ($gateway->slug === 'paypal') {
            return $this->createPayPalOrder($request, $payment, $gateway);
        }

        if ($gateway->slug === 'paddle') {
            return $this->createPaddlePayLink($request, $payment, $plan, $gateway);
        }

        if ($gateway->slug === 'razorpay') {
            return $this->createRazorpayPaymentLink($request, $payment, $plan, $gateway);
        }

        if ($gateway->slug === 'sslcommerz') {
            return $this->createSslCommerzSession($request, $payment, $plan, $gateway);
        }

        if ($gateway->slug === 'coingate') {
            return $this->createCoinGateOrder($request, $payment, $plan, $gateway);
        }

        if ($gateway->slug === 'paystack') {
            return $this->createPaystackTransaction($request, $payment, $gateway);
        }

        if ($gateway->slug === '2checkout') {
            return $this->createTwoCheckoutUrl($request, $payment, $plan, $gateway);
        }

        app(NotificationEventService::class)->transactionPending($payment);

        return redirect()->route('checkout.pending', $payment)
            ->with('info', translate(':gateway payment session is pending gateway integration.', ['gateway' => $gateway->name]));
    }

    public function previewCoupon(Request $request, PlanPriceResolver $resolver, PaymentGatewayManager $gateways): JsonResponse
    {

        $data = $request->validate([
            'plan' => ['required', 'string', 'max:120', 'exists:plans,slug'],
            'billing' => ['required', 'string', 'in:monthly,yearly,lifetime'],
            'coupon' => ['nullable', 'string', 'max:50'],
        ]);

        $plan = Plan::active()
            ->with('countryPrices')
            ->where('slug', $data['plan'])
            ->firstOrFail();
        $countryCode = $request->attributes->get('pricing_country', session('pricing_country'));
        $pricing = $resolver->resolve($plan, $countryCode);
        $coupon = $this->validCoupon($data['coupon'] ?? null, $plan, $request->user());
        $cycle = $this->discountedCycle($pricing[$data['billing']], $coupon);
        $currency = $pricing['currency_code'];
        $planTotal = (float) $cycle['amount'];

        return response()->json([
            'success' => true,
            'coupon' => $coupon ? [
                'code' => $coupon->code,
                'discount_formatted' => CountryCatalog::formatMoney((float) $cycle['discount_amount'], $currency),
            ] : null,
            'summary' => [
                'subtotal_formatted' => CountryCatalog::formatMoney((float) $cycle['subtotal_amount'], $currency),
                'discount_amount' => (float) $cycle['discount_amount'],
                'discount_formatted' => CountryCatalog::formatMoney((float) $cycle['discount_amount'], $currency),
                'vat_amount' => (float) $cycle['vat_amount'],
                'vat_formatted' => CountryCatalog::formatMoney((float) $cycle['vat_amount'], $currency),
                'plan_total_formatted' => CountryCatalog::formatMoney($planTotal, $currency),
            ],
            'gateways' => $gateways->enabled()->mapWithKeys(fn ($gateway) => [
                $gateway->slug => [
                    'fee_amount' => $gateways->processingFee($gateway, $planTotal),
                    'fee_formatted' => CountryCatalog::formatMoney($gateways->processingFee($gateway, $planTotal), $currency),
                    'total_amount' => $gateways->totalWithFee($gateway, $planTotal),
                    'total_formatted' => CountryCatalog::formatMoney($gateways->totalWithFee($gateway, $planTotal), $currency),
                ],
            ]),
        ]);
    }

    public function bankInstructions(Payment $payment): Response
    {
        abort_unless($payment->user_id === auth()->id() && $payment->gateway === 'bank_transfer', 404);

        $gateway = PaymentGateway::where('slug', 'bank_transfer')->where('is_enabled', true)->firstOrFail();

        return Inertia::render('Checkout/BankTransfer', [
            'payment' => $this->paymentPayload($payment),
            'instructions' => $gateway->getCredential('instructions', settings('bank_transfer_instructions', '')),
        ]);
    }

    public function uploadBankProof(BankPaymentProofRequest $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id && $payment->gateway === 'bank_transfer', 404);
        abort_unless($payment->status === 'pending', 422);

        $path = $request->file('proof')->store('payment-proofs/'.$payment->ulid, 'public');
        $metadata = $payment->metadata ?: [];
        $metadata['bank_transfer'] = [
            'proof_path' => $path,
            'proof_disk' => 'public',
            'proof_url' => Storage::disk('public')->url($path),
            'original_name' => $request->file('proof')->getClientOriginalName(),
            'reference' => $request->validated('reference'),
            'note' => $request->validated('note'),
            'uploaded_at' => now()->toISOString(),
        ];

        $payment->update(['metadata' => $metadata]);

        app(NotificationEventService::class)->transactionPending($payment);

        return redirect()->route('checkout.pending', $payment)
            ->with('success', translate('Payment proof uploaded. Your transaction is pending review.'));
    }

    public function pending(Payment $payment): Response
    {
        abort_unless($payment->user_id === auth()->id(), 404);

        return Inertia::render('Checkout/Pending', [
            'payment' => $this->paymentPayload($payment),
        ]);
    }

    public function paypalReturn(Request $request, Payment $payment, PaymentActivationService $activation): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id && $payment->gateway === 'paypal', 404);

        if ($payment->status === 'completed') {
            return redirect()->route('checkout.pending', $payment);
        }

        $gateway = PaymentGateway::where('slug', 'paypal')->where('is_enabled', true)->firstOrFail();
        $token = $this->payPalAccessToken($gateway);

        if (! $token || ! $payment->gateway_payment_id) {
            return redirect()->route('checkout.pending', $payment)
                ->with('error', translate('PayPal payment could not be captured.'));
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->withBody('{}', 'application/json')
            ->post($this->payPalBaseUrl($gateway).'/v2/checkout/orders/'.$payment->gateway_payment_id.'/capture');

        if ($response->failed()) {
            return redirect()->route('checkout.pending', $payment)
                ->with('error', $response->json('message', translate('PayPal payment could not be captured.')));
        }

        $captureId = data_get($response->json(), 'purchase_units.0.payments.captures.0.id', $payment->gateway_payment_id);
        $activation->activateFromPayment($payment, $captureId);

        return redirect()->route('checkout.pending', $payment)
            ->with('success', translate('Payment confirmed successfully.'));
    }

    private function createTrialSubscription(Request $request, Plan $plan, string $billing, PaymentGateway $gateway, array $cycle, array $pricing): GatewaySubscription
    {
        return DB::transaction(function () use ($request, $plan, $billing, $gateway, $cycle, $pricing) {
            // Prevent trial abuse
            if ($request->user()->has_trialed) {
                throw new \Exception(translate('You have already used your free trial.'));
            }

            $trialEndsAt = now()->addDays((int) ($cycle['trial_days'] ?: 30));

            $subscription = GatewaySubscription::create([
                'user_id' => $request->user()->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billing,
                'status' => 'trialing',
                'gateway' => $gateway->slug,
                'amount' => 0,
                'currency' => $pricing['currency_code'],
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => now(),
                'current_period_end' => $trialEndsAt,
            ]);

            $request->user()->update([
                'plan_id' => $plan->id,
                'subscription_status' => 'trialing',
                'trial_ends_at' => $trialEndsAt,
                'subscription_ends_at' => $trialEndsAt,
                'has_trialed' => true,
            ]);

            return $subscription;
        });
    }

    private function paymentMetadata(string $billing, array $pricing, array $cycle, PaymentGateway $gateway, float $fee, float $total, ?Coupon $coupon = null): array
    {
        return [
            'billing_cycle' => $billing,
            'pricing_country' => $pricing['country_code'],
            'pricing_source' => $pricing['source'],
            'discount_amount' => $cycle['discount_amount'] ?? 0,
            'coupon' => $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'is_recurring' => $coupon->is_recurring,
            ] : null,
            'subtotal_amount' => $cycle['subtotal_amount'],
            'vat_amount' => $cycle['vat_amount'],
            'vat_percentage' => $cycle['vat_percentage'],
            'plan_total_amount' => $cycle['amount'],
            'processing_fee_amount' => $fee,
            'processing_fee_type' => $gateway->processing_fee_type,
            'processing_fee_value' => $gateway->processing_fee_value,
            'total_amount' => $total,
            'is_test_mode' => $gateway->is_test_mode,
            'created_from' => 'checkout_session',
        ];
    }

    private function paymentPayload(Payment $payment): array
    {
        $payment->loadMissing('plan');
        $metadata = $payment->metadata ?: [];
        $currency = $payment->currency;

        return [
            'ulid' => $payment->ulid,
            'status' => $payment->status,
            'gateway' => $payment->gateway,
            'amount' => (float) $payment->amount,
            'currency' => $currency,
            'formatted_amount' => CountryCatalog::formatMoney((float) $payment->amount, $currency),
            'subtotal_amount' => (float) ($metadata['subtotal_amount'] ?? 0),
            'subtotal_formatted' => CountryCatalog::formatMoney((float) ($metadata['subtotal_amount'] ?? 0), $currency),
            'discount_amount' => (float) ($metadata['discount_amount'] ?? 0),
            'discount_formatted' => CountryCatalog::formatMoney((float) ($metadata['discount_amount'] ?? 0), $currency),
            'vat_amount' => (float) ($metadata['vat_amount'] ?? 0),
            'vat_percentage' => (float) ($metadata['vat_percentage'] ?? 0),
            'vat_formatted' => CountryCatalog::formatMoney((float) ($metadata['vat_amount'] ?? 0), $currency),
            'plan_total_amount' => (float) ($metadata['plan_total_amount'] ?? $payment->amount),
            'plan_total_formatted' => CountryCatalog::formatMoney((float) ($metadata['plan_total_amount'] ?? $payment->amount), $currency),
            'processing_fee_amount' => (float) ($metadata['processing_fee_amount'] ?? 0),
            'processing_fee_formatted' => CountryCatalog::formatMoney((float) ($metadata['processing_fee_amount'] ?? 0), $currency),
            'plan' => ['name' => $payment->plan?->name, 'slug' => $payment->plan?->slug],
            'billing_cycle' => $metadata['billing_cycle'] ?? null,
            'proof_uploaded' => filled(data_get($metadata, 'bank_transfer.proof_path')),
            'created_at' => $payment->created_at?->toISOString(),
        ];
    }

    private function createPayPalOrder(Request $request, Payment $payment, PaymentGateway $gateway): RedirectResponse
    {
        $token = $this->payPalAccessToken($gateway);
        if (! $token) { $payment->update(['status' => 'failed']); return back()->with('error', translate('PayPal is not configured.')); }

        $baseUrl = $this->payPalBaseUrl($gateway);
        $response = Http::withToken($token)->acceptJson()->post($baseUrl.'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $payment->ulid,
                'custom_id' => $payment->ulid,
                'amount' => ['currency_code' => strtoupper($payment->currency), 'value' => number_format((float) $payment->amount, 2, '.', '')],
            ]],
            'application_context' => [
                'return_url' => $this->absoluteUrl($request, '/checkout/paypal/return/'.$payment->ulid),
                'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$payment->plan?->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
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

        if (! $approveUrl) { return redirect()->route('checkout.pending', $payment)->with('warning', translate('PayPal order was created, but approval URL was not returned.')); }
        return redirect()->away($approveUrl);
    }

    private function payPalAccessToken(PaymentGateway $gateway): ?string
    {
        $clientId = $gateway->getCredential('client_id');
        $clientSecret = $gateway->getCredential('client_secret');
        if (! $clientId || ! $clientSecret) { return null; }
        $response = Http::withBasicAuth($clientId, $clientSecret)->asForm()->post($this->payPalBaseUrl($gateway).'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        return $response->successful() ? $response->json('access_token') : null;
    }

    private function payPalBaseUrl(PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private function createPaddlePayLink(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $vendorId = $gateway->getCredential('vendor_id');
        $authCode = $gateway->getCredential('api_key');
        if (! $vendorId || ! $authCode) { return $this->failGatewaySession($payment, 'Paddle is not configured.'); }

        $response = Http::asForm()->post('https://vendors.paddle.com/api/2.0/product/generate_pay_link', [
            'vendor_id' => $vendorId, 'vendor_auth_code' => $authCode, 'title' => $plan->name,
            'prices' => [strtoupper($payment->currency).':'.number_format((float) $payment->amount, 2, '.', '')],
            'quantity' => 1, 'return_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'webhook_url' => $this->absoluteUrl($request, '/webhooks/paddle'),
            'passthrough' => json_encode(['payment_ulid' => $payment->ulid]),
        ]);

        if ($response->failed() || ! $response->json('success')) { return $this->failGatewaySession($payment, $response->json('error.message', 'Paddle pay link failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('response.product_id'), 'metadata' => [...($payment->metadata ?: []), 'gateway_pay_link' => $response->json('response.url')]]);
        return redirect()->away($response->json('response.url'));
    }

    private function createRazorpayPaymentLink(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $keyId = $gateway->getCredential('key_id');
        $keySecret = $gateway->getCredential('key_secret');
        if (! $keyId || ! $keySecret) { return $this->failGatewaySession($payment, 'Razorpay is not configured.'); }

        $response = Http::withBasicAuth($keyId, $keySecret)->acceptJson()->post('https://api.razorpay.com/v1/payment_links', [
            'amount' => $this->minorAmount((float) $payment->amount, $payment->currency), 'currency' => strtoupper($payment->currency),
            'description' => $plan->name, 'reference_id' => $payment->ulid,
            'callback_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid), 'callback_method' => 'get',
            'customer' => ['name' => $request->user()->name, 'email' => $request->user()->email],
            'notes' => ['payment_ulid' => $payment->ulid],
        ]);

        if ($response->failed()) { return $this->failGatewaySession($payment, $response->json('error.description', 'Razorpay payment link failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('id'), 'metadata' => [...($payment->metadata ?: []), 'gateway_payment_link' => $response->json('short_url')]]);
        return redirect()->away($response->json('short_url'));
    }

    private function createSslCommerzSession(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $storeId = $gateway->getCredential('store_id');
        $storePassword = $gateway->getCredential('store_password');
        if (! $storeId || ! $storePassword) { return $this->failGatewaySession($payment, translate('SSLCommerz is not configured.')); }

        $baseUrl = $gateway->is_test_mode ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
        $response = Http::asForm()->post($baseUrl.'/gwprocess/v4/api.php', [
            'store_id' => $storeId, 'store_passwd' => $storePassword, 'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => strtoupper($payment->currency), 'tran_id' => $payment->ulid,
            'success_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'fail_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$plan->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
            'ipn_url' => $this->absoluteUrl($request, '/webhooks/sslcommerz'),
            'cus_name' => $request->user()->name, 'cus_email' => $request->user()->email,
            'cus_add1' => 'N/A', 'cus_city' => 'N/A', 'cus_country' => 'N/A', 'cus_phone' => 'N/A',
            'shipping_method' => 'NO', 'product_name' => $plan->name, 'product_category' => 'Subscription', 'product_profile' => 'non-physical-goods',
        ]);

        if ($response->failed() || ! $response->json('GatewayPageURL')) { return $this->failGatewaySession($payment, $response->json('failedreason', 'SSLCommerz session failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('sessionkey'), 'metadata' => [...($payment->metadata ?: []), 'gateway_session_id' => $response->json('sessionkey')]]);
        return redirect()->away($response->json('GatewayPageURL'));
    }

    private function createCoinGateOrder(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $token = $gateway->getCredential('auth_token');
        if (! $token) { return $this->failGatewaySession($payment, 'CoinGate is not configured.'); }

        $baseUrl = $gateway->is_test_mode ? 'https://api-sandbox.coingate.com/v2' : 'https://api.coingate.com/v2';
        $webhookToken = bin2hex(random_bytes(16));
        $response = Http::withToken($token)->acceptJson()->asForm()->post($baseUrl.'/orders', [
            'order_id' => $payment->ulid, 'price_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'price_currency' => strtoupper($payment->currency), 'receive_currency' => strtoupper($payment->currency),
            'title' => $plan->name, 'callback_url' => $this->absoluteUrl($request, '/webhooks/coingate?token='.$webhookToken),
            'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$plan->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
            'success_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
        ]);

        if ($response->failed()) { return $this->failGatewaySession($payment, $response->json('message', 'CoinGate order failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('id'), 'metadata' => [...($payment->metadata ?: []), 'coingate_webhook_token' => $webhookToken]]);
        return redirect()->away($response->json('payment_url'));
    }

    private function createPaystackTransaction(Request $request, Payment $payment, PaymentGateway $gateway): RedirectResponse
    {
        $secretKey = $gateway->getCredential('secret_key');
        if (! $secretKey) { return $this->failGatewaySession($payment, 'Paystack is not configured.'); }

        $response = Http::withToken($secretKey)->acceptJson()->post('https://api.paystack.co/transaction/initialize', [
            'email' => $request->user()->email, 'amount' => $this->minorAmount((float) $payment->amount, $payment->currency),
            'currency' => strtoupper($payment->currency), 'reference' => $payment->ulid,
            'callback_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'metadata' => ['payment_ulid' => $payment->ulid],
        ]);

        if ($response->failed() || ! $response->json('status')) { return $this->failGatewaySession($payment, $response->json('message', 'Paystack transaction failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('data.reference'), 'metadata' => [...($payment->metadata ?: []), 'gateway_access_code' => $response->json('data.access_code')]]);
        return redirect()->away($response->json('data.authorization_url'));
    }

    private function createTwoCheckoutUrl(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $merchantCode = $gateway->getCredential('merchant_code');
        if (! $merchantCode) { return $this->failGatewaySession($payment, '2Checkout is not configured.'); }

        $baseUrl = $gateway->is_test_mode ? 'https://sandbox.2checkout.com/checkout/buy' : 'https://secure.2checkout.com/checkout/buy';
        $query = http_build_query([
            'merchant' => $merchantCode, 'dynamic' => 1, 'prod' => $plan->name,
            'price' => number_format((float) $payment->amount, 2, '.', ''), 'qty' => 1,
            'currency' => strtoupper($payment->currency), 'merchant_order_id' => $payment->ulid,
            'return-url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'x_receipt_link_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
        ]);

        $payment->update(['gateway_payment_id' => $payment->ulid]);
        return redirect()->away($baseUrl.'?'.$query);
    }

    private function failGatewaySession(Payment $payment, string $message): RedirectResponse
    {
        $payment->update(['status' => 'failed', 'metadata' => [...($payment->metadata ?: []), 'gateway_error' => $message]]);
        return back()->with('error', translate($message));
    }

    private function minorAmount(float $amount, string $currency): int
    {
        $zeroDecimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'];
        return in_array(strtoupper($currency), $zeroDecimal, true) ? (int) round($amount) : (int) round($amount * 100);
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }

    private function validCoupon(?string $code, Plan $plan, User $user): ?Coupon
    {
        if (! $code) { return null; }
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        if (! $coupon || ! $coupon->isValid()) { abort(422, translate('Coupon is invalid or expired.')); }
        if ($coupon->plan_id && (int) $coupon->plan_id !== (int) $plan->id) { abort(422, translate('Coupon is not valid for this plan.')); }
        if (! $coupon->isEligibleForUser($user)) { abort(422, translate('Coupon is not valid for your account.')); }
        return $coupon;
    }

    private function discountedCycle(array $cycle, ?Coupon $coupon): array
    {
        $discount = $coupon ? $coupon->calculateDiscount((float) $cycle['subtotal_amount']) : 0.0;
        $discountedSubtotal = max(0, (float) $cycle['subtotal_amount'] - $discount);
        $vatAmount = round(($discountedSubtotal * (float) $cycle['vat_percentage']) / 100, 2);

        return [
            ...$cycle,
            'subtotal_amount' => $discountedSubtotal,
            'vat_amount' => $vatAmount,
            'amount' => $cycle['is_trial'] ? 0.0 : round($discountedSubtotal + $vatAmount, 2),
            'discount_amount' => $discount,
        ];
    }
}
