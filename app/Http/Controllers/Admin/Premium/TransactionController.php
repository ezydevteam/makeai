<?php

namespace App\Http\Controllers\Admin\Premium;

use App\Http\Controllers\Concerns\AuthorizesAdminActions;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentActivationService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    use AuthorizesAdminActions;

    public function index(Request $request): Response
    {
        $filters = [
            'status' => trim((string) $request->string('status')->value()),
            'gateway' => trim((string) $request->string('gateway')->value()),
            'type' => trim((string) $request->string('type')->value()),
            'search' => trim((string) $request->string('search')->value()),
        ];

        $payments = Payment::query()
            ->with(['user:id,ulid,name,email', 'plan:id,name'])
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['gateway'] !== '', fn ($query) => $query->where('gateway', $filters['gateway']))
            ->when($filters['type'] !== '', fn ($query) => $query->where('type', $filters['type']))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('ulid', 'like', '%'.$filters['search'].'%')
                        ->orWhere('gateway_payment_id', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('user', function ($query) use ($filters) {
                            $query->where('name', 'like', '%'.$filters['search'].'%')
                                ->orWhere('email', 'like', '%'.$filters['search'].'%');
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Transactions
        $totalCurrent = Payment::count();
        $totalPrevious = Payment::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Pending Transactions
        $pendingCurrent = Payment::where('status', 'pending')->count();
        $pendingPrevious = Payment::where('status', 'pending')->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Completed Transactions
        $completedCurrent = Payment::where('status', 'completed')->count();
        $completedPrevious = Payment::where('status', 'completed')->where('created_at', '<', $sevenDaysAgo)->count();

        // 4. Total Transaction Amount (Revenue)
        $revenueCurrent = (float) Payment::where('status', 'completed')->sum('amount');
        $revenuePrevious = (float) Payment::where('status', 'completed')->where('created_at', '<', $sevenDaysAgo)->sum('amount');

        $stats = [
            'total' => [
                'value' => $totalCurrent,
                'comparison' => $this->calculateComparison($totalCurrent, $totalPrevious),
            ],
            'pending' => [
                'value' => $pendingCurrent,
                'comparison' => $this->calculateComparison($pendingCurrent, $pendingPrevious),
            ],
            'completed' => [
                'value' => $completedCurrent,
                'comparison' => $this->calculateComparison($completedCurrent, $completedPrevious),
            ],
            'revenue' => [
                'value' => format_currency($revenueCurrent),
                'comparison' => $this->calculateComparison($revenueCurrent, $revenuePrevious),
            ],
        ];

        $gateways = Payment::query()
            ->select('gateway')
            ->whereNotNull('gateway')
            ->where('gateway', '!=', '')
            ->distinct()
            ->orderBy('gateway')
            ->pluck('gateway')
            ->map(fn (string $gateway) => [
                'value' => $gateway,
                'label' => str($gateway)->replace(['_', '-'], ' ')->title()->toString(),
            ])
            ->values();

        return Inertia::render('Admin/Premium/Transactions', [
            'payments' => [
                'data' => collect($payments->items())->map(fn (Payment $payment) => $this->paymentPayload($payment))->values(),
                'links' => $payments->linkCollection(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
                'total' => $payments->total(),
            ],
            'filters' => $filters,
            'gateways' => $gateways,
            'stats' => $stats,
        ]);
    }

    public function approve(Payment $payment, PaymentActivationService $activation): RedirectResponse
    {
        $this->authorizeAdmin('payments.gateways');

        if ($payment->status !== 'pending') {
            return back()->with('error', translate('Only pending transactions can be approved.'));
        }

        $metadata = $payment->metadata ?: [];
        $metadata['admin_review'] = [
            'action' => 'approved',
            'admin_id' => auth('admin')->id(),
            'reviewed_at' => now()->toISOString(),
        ];
        $payment->update(['metadata' => $metadata]);

        $gatewayPaymentId = data_get($metadata, 'bank_transfer.reference') ?: 'manual-'.$payment->ulid;

        if ($payment->type === 'credit_topup') {
            $activation->activateCreditTopup($payment, $gatewayPaymentId);
        } else {
            $activation->activateFromPayment($payment, $gatewayPaymentId);
        }

        return back()->with('success', translate('Transaction approved and activated.'));
    }

    public function reject(Request $request, Payment $payment, SubscriptionLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeAdmin('payments.gateways');

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($payment->status !== 'pending') {
            return back()->with('error', translate('Only pending transactions can be rejected.'));
        }

        $metadata = $payment->metadata ?: [];
        $metadata['admin_review'] = [
            'action' => 'rejected',
            'note' => $data['note'] ?? null,
            'admin_id' => auth('admin')->id(),
            'reviewed_at' => now()->toISOString(),
        ];
        $payment->update(['metadata' => $metadata]);

        $lifecycle->rejectPayment($payment, $data['note'] ?? null);

        return back()->with('success', translate('Transaction rejected.'));
    }

    /**
     * Stream a bank-transfer payment proof. Access is gated by the route's
     * payments permission + admin auth, so proofs never need to be public.
     */
    public function proof(Payment $payment): StreamedResponse
    {
        $path = data_get($payment->metadata, 'bank_transfer.proof_path');
        $disk = data_get($payment->metadata, 'bank_transfer.proof_disk', 'local');

        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }

    private function paymentPayload(Payment $payment): array
    {
        $metadata = $payment->metadata ?: [];

        return [
            'id' => $payment->id,
            'ulid' => $payment->ulid,
            'gateway' => $payment->gateway,
            'gateway_payment_id' => $payment->gateway_payment_id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'type' => $payment->type,
            'billing_cycle' => $metadata['billing_cycle'] ?? null,
            'total_credits' => $metadata['total_credits'] ?? null,
            'created_at' => $payment->created_at?->toISOString(),
            'user' => $payment->user ? [
                'ulid' => $payment->user->ulid,
                'name' => $payment->user->name,
                'email' => $payment->user->email,
            ] : null,
            'plan' => $payment->plan ? [
                'id' => $payment->plan->id,
                'name' => $payment->plan->name,
            ] : null,
            'bank_transfer' => data_get($metadata, 'bank_transfer') ? [
                'proof_url' => data_get($metadata, 'bank_transfer.proof_path')
                    ? route('admin.transactions.proof', $payment)
                    : data_get($metadata, 'bank_transfer.proof_url'),
                'original_name' => data_get($metadata, 'bank_transfer.original_name'),
                'reference' => data_get($metadata, 'bank_transfer.reference'),
                'note' => data_get($metadata, 'bank_transfer.note'),
                'uploaded_at' => data_get($metadata, 'bank_transfer.uploaded_at'),
            ] : null,
            'admin_review' => data_get($metadata, 'admin_review'),
            'gateway_error' => data_get($metadata, 'gateway_error'),
        ];
    }

    private function calculateComparison(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'label' => $current === 0 ? '0%' : '+100%',
                'type' => $current === 0 ? 'neutral' : 'up',
            ];
        }

        $delta = (($current - $previous) / $previous) * 100;
        $rounded = (int) round(abs($delta));

        if ($rounded === 0) {
            return [
                'label' => '0%',
                'type' => 'neutral',
            ];
        }

        return [
            'label' => ($delta > 0 ? '+' : '-') . $rounded . '%',
            'type' => $delta > 0 ? 'up' : 'down',
        ];
    }
}
