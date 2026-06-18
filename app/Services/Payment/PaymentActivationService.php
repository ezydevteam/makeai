<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\NotificationEventService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentActivationService extends SubscriptionLifecycleService
{
    /**
     * Activate a credit top-up payment.
     * Adds credits to the user's account and marks payment as completed.
     */
    public function activateCreditTopup(Payment $payment, string $gatewayPaymentId): void
    {
        DB::transaction(function () use ($payment, $gatewayPaymentId) {
            // Idempotency check
            if ($payment->status === 'completed') {
                return;
            }

            $metadata = $payment->metadata ?: [];
            $totalCredits = (int) ($metadata['total_credits'] ?? 0);
            $baseCredits = (int) ($metadata['base_credits'] ?? 0);
            $bonusCredits = (int) ($metadata['bonus_credits'] ?? 0);

            if ($totalCredits <= 0) {
                Log::error('Credit topup activation failed: invalid credits amount', [
                    'payment_id' => $payment->id,
                    'metadata' => $metadata,
                ]);
                return;
            }

            // Add credits to user
            $user = $payment->user;
            $user->addCredits($totalCredits, 'topup', 'Credit top-up purchase', [
                'payment_id' => $payment->id,
                'base_credits' => $baseCredits,
                'bonus_credits' => $bonusCredits,
                'amount_paid' => $payment->amount,
                'currency' => $payment->currency,
            ]);

            // Mark payment as completed
            $payment->update([
                'status' => 'completed',
                'gateway_payment_id' => $gatewayPaymentId,
            ]);

            // Send notification
            app(NotificationEventService::class)->creditsAdded($user, $totalCredits, 'Credit top-up');
        });
    }
}
