<?php

namespace App\Http\Controllers\User;

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
            'subscription' => [
                'status' => $user->subscription_status,
                'ends_at' => $user->subscription_ends_at?->toISOString(),
                'trial_ends_at' => $user->trial_ends_at?->toISOString(),
            ],
        ]);
    }
}
