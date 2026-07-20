<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    /**
     * Unified webhook receiver that dispatches processing to a queued job.
     * This ensures a fast 200 OK response, preventing timeout and retry spam
     * from providers that require synchronous outbound HTTP validation (e.g., PayPal, SSLCommerz).
     *
     * The raw body and headers are captured because gateway signature schemes
     * (Razorpay, Paystack, PayPal) sign the raw payload and transmit the
     * signature via HTTP headers, which are lost once the request is consumed.
     */
    public function handle(string $gateway, Request $request): JsonResponse
    {
        ProcessPaymentWebhookJob::dispatch(
            $gateway,
            $request->all(),
            (string) $request->getContent(),
            collect($request->headers->all())
                ->map(fn (array $values) => (string) ($values[0] ?? ''))
                ->all(),
        );

        return response()->json(['received' => true], 200);
    }
}
