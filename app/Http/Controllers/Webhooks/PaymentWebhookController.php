<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    /**
     * Unified webhook receiver that dispatches processing to a queued job.
     * This ensures a sub-50ms 200 OK response, preventing timeout and retry spam 
     * from providers that require synchronous outbound HTTP validation (e.g., PayPal, SSLCommerz).
     */
    public function handle(string $gateway, Request $request): JsonResponse
    {
        // Dispatch to queue immediately for fast 200 OK response
        ProcessPaymentWebhookJob::dispatch($gateway, $request->all())->onQueue('webhooks');

        return response()->json(['received' => true], 200);
    }
}
