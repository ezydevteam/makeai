<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Billing is only meaningful when purchasing is available, or the user has
        // history to view/manage (existing subscriber on a toggled-off install, or a
        // past payer). A premium-off user who never transacted has nothing here.
        if (! isProAvailable()
            && ! Payment::where('user_id', $user->id)->exists()
            && ! GatewaySubscription::where('user_id', $user->id)->exists()
        ) {
            return redirect()->route('user.dashboard');
        }

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

        $activeSubscription = GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->with('scheduledPlan:id,name,slug')
            ->latest()
            ->first();

        $resumableSubscription = $activeSubscription ? null : GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->where('status', GatewaySubscription::STATUS_CANCELLED)
            ->where(function ($query) {
                $query->whereNull('current_period_end')->orWhere('current_period_end', '>', now());
            })
            ->latest('cancelled_at')
            ->first();

        $canResume = $resumableSubscription
            && (! $resumableSubscription->gateway_subscription_id
                || in_array($resumableSubscription->gateway, ['stripe', 'paypal'], true));

        // Only a recurring subscription (a gateway subscription id on file) auto-
        // renews and can be cancelled. A one-time payment just runs to its period
        // end and stops — nothing to cancel.
        $isRecurring = (bool) $activeSubscription?->gateway_subscription_id;

        // A premium plan with no gateway subscription at all = admin-granted /
        // comped access. There's nothing for the user to cancel or resume; only an
        // admin can change it. Surface a note instead of missing actions.
        $isManagedExternally = (bool) ($user->plan
            && ! $user->plan->is_free
            && ! $activeSubscription
            && ! $resumableSubscription);

        // A paid, non-recurring, non-lifetime subscription: reassure the user they
        // won't be charged again.
        $isOneTime = (bool) ($activeSubscription
            && ! $isRecurring
            && $activeSubscription->billing_cycle !== 'lifetime');

        return Inertia::render('User/Billing', [
            'payments' => $payments,
            'plan' => $this->planData($user),
            'subscription' => [
                'status' => $user->subscription_status,
                'ends_at' => $user->subscription_ends_at?->toISOString(),
                'trial_ends_at' => $user->trial_ends_at?->toISOString(),
                'gateway' => $activeSubscription?->gateway ?? $resumableSubscription?->gateway,
                'billing_cycle' => $activeSubscription?->billing_cycle,
                'can_cancel' => (bool) ($activeSubscription && $isRecurring && $activeSubscription->billing_cycle !== 'lifetime'),
                'can_resume' => (bool) $canResume,
                'has_billing_portal' => $activeSubscription?->gateway === 'stripe' && (bool) config('cashier.secret'),
                'is_managed_externally' => $isManagedExternally,
                'is_one_time' => $isOneTime,
                'scheduled_plan_name' => $activeSubscription?->scheduledPlan?->name,
                'scheduled_change_at' => $activeSubscription?->scheduled_change_at?->toISOString(),
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
        ];
    }

    /**
     * Stream a PDF invoice for a single payment.
     *
     * Ownership is checked explicitly rather than left to route-model binding: the Payment
     * model binds by ulid, which is unguessable but still only an identifier — without this
     * check any signed-in user could fetch another account's invoice by id.
     */
    public function invoice(Request $request, Payment $payment): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $payment->loadMissing('plan:id,name,slug');

        $pdf = app(ExportService::class)->renderPdfString('billing.invoice', [
            'payment' => $payment,
            'user' => $request->user(),
            'invoiceNumber' => $this->invoiceNumber($payment),
            'formattedAmount' => format_currency((float) $payment->amount, $payment->currency),
            'gatewayLabel' => Str::headline($payment->gateway ?: 'manual'),
            'lineItem' => $this->invoiceLineItem($payment),
            'company' => [
                'name' => settings('site_name', config('app.name')),
                'email' => settings('mail_from_address', ''),
                'url' => settings('site_url', config('app.url')),
                'support' => settings('site_support_email', ''),
                'logo' => $this->invoiceLogoPath(),
            ],
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->invoiceNumber($payment).'.pdf"',
        ]);
    }

    /**
     * Absolute filesystem path to the site logo, or null to fall back to the site name.
     *
     * mPDF renders server-side, so it cannot follow the root-relative URL media_url() builds
     * — it needs a real path on disk. A logo stored as an external URL is deliberately NOT
     * used: fetching it would make invoice rendering depend on an outbound request (slow,
     * and a fetch of a stored URL from inside our own process). The name renders instead.
     */
    private function invoiceLogoPath(): ?string
    {
        // Light logo first: the invoice is printed on white.
        $stored = settings('site_logo_light', '') ?: settings('site_logo_dark', '');

        if (blank($stored) || preg_match('#^(https?:)?//#i', $stored) || str_starts_with($stored, 'data:')) {
            return null;
        }

        $relative = ltrim($stored, '/');

        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($relative);

        // SVG is skipped: mPDF's SVG support is partial and a logo it cannot parse renders as
        // a broken box in the middle of an invoice.
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
            return null;
        }

        return $path;
    }

    /**
     * Stable, human-readable invoice number derived from the payment itself, so the same
     * payment always produces the same number no matter when it is downloaded.
     */
    private function invoiceNumber(Payment $payment): string
    {
        return 'INV-'.$payment->created_at->format('Ym').'-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{title: string, subtitle: string|null}
     */
    private function invoiceLineItem(Payment $payment): array
    {
        $period = $payment->created_at->format('F j, Y');

        return match ($payment->type) {
            'subscription' => [
                'title' => translate(':plan subscription', ['plan' => $payment->plan?->name ?? translate('Plan')]),
                'subtitle' => translate('Billed on :date', ['date' => $period]),
            ],
            'credit_topup' => [
                'title' => translate('Credit top-up'),
                'subtitle' => translate('Purchased on :date', ['date' => $period]),
            ],
            'bank_transfer' => [
                'title' => translate('Bank transfer'),
                'subtitle' => translate('Received on :date', ['date' => $period]),
            ],
            default => [
                'title' => $payment->plan?->name
                    ? translate(':plan purchase', ['plan' => $payment->plan->name])
                    : translate('One-time purchase'),
                'subtitle' => translate('Purchased on :date', ['date' => $period]),
            ],
        };
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
