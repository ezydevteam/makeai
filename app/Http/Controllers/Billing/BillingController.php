<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->with('plan:id,name,slug')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'ulid' => $payment->ulid,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'type' => $payment->type,
                'gateway' => $payment->gateway,
                'plan_name' => $payment->plan?->name,
                'created_at' => $payment->created_at->toISOString(),
            ]);

        return Inertia::render('User/Billing', [
            'payments' => $payments,
            'plan' => $this->planData($user),
            'subscription' => [
                'status' => $user->subscription_status,
                'ends_at' => $user->subscription_ends_at?->toISOString(),
                'trial_ends_at' => $user->trial_ends_at?->toISOString(),
            ],
        ]);
    }

    private function planData(User $user): ?array
    {
        $plan = $user->plan;

        if (! $plan) {
            return null;
        }

        return [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'is_free' => (bool) $plan->is_free,
            'features' => $this->normalizePlanFeatures($plan->features),
            'subscription_status' => $user->subscription_status,
            'subscription_ends_at' => optional($user->subscription_ends_at)->toISOString(),
            'trial_ends_at' => optional($user->trial_ends_at)->toISOString(),
            'daily_limit' => $user->daily_limit ? (float) $user->daily_limit : null,
            'monthly_limit' => $user->monthly_limit ? (float) $user->monthly_limit : null,
        ];
    }

    private function normalizePlanFeatures(mixed $features): array
    {
        if (is_array($features)) {
            return array_values(array_filter(array_map(
                static fn (mixed $feature): string => trim((string) $feature),
                $features
            )));
        }

        if (is_string($features)) {
            $decoded = json_decode($features, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map(
                    static fn (mixed $feature): string => trim((string) $feature),
                    $decoded
                )));
            }

            return array_values(array_filter(array_map(
                static fn (string $feature): string => trim($feature),
                preg_split('/[\r\n,]+/', $features) ?: []
            )));
        }

        return [];
    }
}
