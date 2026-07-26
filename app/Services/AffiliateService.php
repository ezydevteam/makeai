<?php

namespace App\Services;

use App\Models\AffiliateCommission;
use App\Models\AffiliateProgram;
use App\Models\AffiliateReferral;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AffiliateService
{
    public function program(): AffiliateProgram
    {
        return AffiliateProgram::current();
    }

    public function isEnabled(): bool
    {
        // Affiliate is a monetization feature (commissions on paid purchases,
        // gateway payouts), so it requires the Extended License like the rest of
        // the paid system — on top of the admin toggle. The toggle defaults ON so a
        // fresh Extended-license install shows the Affiliate Program by default
        // (mirrors coupons_enabled); an admin can still switch it off.
        return is_extended_license() && (bool) settings('affiliate_enabled', true);
    }

    /**
     * Determine whether a payment type is eligible for commission based on
     * the configured `commission_on` mode.
     */
    protected function paymentQualifiesForCommission(Payment $payment, AffiliateProgram $program): bool
    {
        $type = $payment->type ?? 'subscription';

        return match ($program->commission_on) {
            'subscription' => $type === 'subscription',
            'all_purchases' => true,
            default => true,
        };
    }

    public function captureVisit(Request $request, string $code): ?AffiliateReferral
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $referrer = $this->findReferrer($code);

        if (! $referrer || $referrer->affiliate_banned) {
            return null;
        }

        // Self-referral prevention: a logged-in user clicking their own link
        // should not inflate their own click stats or re-attributed themselves.
        $visitor = $request->user();
        if ($visitor && $visitor->is($referrer)) {
            return null;
        }

        $program = $this->program();
        $request->session()->put('affiliate_ref', $referrer->referral_code);

        cookie()->queue(cookie(
            'affiliate_ref',
            $referrer->referral_code,
            max(1, (int) $program->cookie_days) * 24 * 60,
            null,
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));

        // Deduplicate clicks: one click record per referrer + IP per 24h window.
        // This prevents click inflation from repeated hits while still recording
        // genuine returning visitors after the window elapses.
        $existing = AffiliateReferral::query()
            ->where('referrer_id', $referrer->id)
            ->where('ip_address', $request->ip())
            ->where('landed_at', '>=', now()->subDay())
            ->latest('landed_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return AffiliateReferral::create([
            'referrer_id' => $referrer->id,
            'referral_code' => $referrer->referral_code,
            'ip_address' => $request->ip(),
            'landed_at' => now(),
        ]);
    }

    public function attachReferralToUser(Request $request, User $user): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $code = $request->session()->get('affiliate_ref') ?: $request->cookie('affiliate_ref');
        $referrer = $code ? $this->findReferrer((string) $code) : null;

        if (! $referrer || $referrer->is($user) || $referrer->affiliate_banned) {
            return;
        }

        // Self-referral guard: a freshly created account registering from the
        // same IP as the referrer's last login is almost certainly the same
        // person creating a second account to farm their own commission.
        if ($referrer->last_login_ip && $request->ip() === $referrer->last_login_ip) {
            return;
        }

        DB::transaction(function () use ($request, $user, $referrer) {
            $user->forceFill(['referred_by' => $referrer->id])->save();

            // Match by IP AND referral code (conjunction) so each visitor is
            // attributed to their own click record rather than any unconverted
            // click for the same referrer.
            $referral = AffiliateReferral::query()
                ->where('referrer_id', $referrer->id)
                ->whereNull('referred_id')
                ->where(function ($query) use ($request, $referrer) {
                    $query->where('ip_address', $request->ip())
                        ->where('referral_code', $referrer->referral_code);
                })
                ->latest()
                ->first();

            $referral?->update([
                'referred_id' => $user->id,
                'converted_at' => now(),
            ]);
        });
    }

    public function createCommissionForPayment(Payment $payment): ?AffiliateCommission
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $payment->loadMissing(['user', 'plan']);
        $user = $payment->user;

        if (! $user || ! $user->referred_by) {
            return null;
        }

        $program = $this->program();

        if (! $this->paymentQualifiesForCommission($payment, $program)) {
            return null;
        }

        if ($program->commission_on === 'first_purchase' && $this->hasEarlierCompletedPayment($payment)) {
            return null;
        }

        if (AffiliateCommission::where('order_id', $payment->id)->exists()) {
            return null;
        }

        $referrer = User::find($user->referred_by);

        if (! $referrer || $referrer->affiliate_banned) {
            return null;
        }

        // Normalize the order amount to the store base currency before taking a
        // percentage, so per-country (foreign-currency) payments don't produce
        // commissions that are later summed/paid as if they were base currency.
        $orderAmountBase = $this->orderAmountInBaseCurrency($payment);
        $amount = $this->calculateCommissionAmount($orderAmountBase, $program);

        if ($amount <= 0) {
            return null;
        }

        try {
            return DB::transaction(function () use ($payment, $user, $referrer, $program, $amount) {
                $commission = AffiliateCommission::create([
                    'referrer_id' => $referrer->id,
                    'referred_id' => $user->id,
                    'order_id' => $payment->id,
                    'amount' => $amount,
                    'status' => $program->auto_approve_commissions ? 'approved' : 'pending',
                    'approved_at' => $program->auto_approve_commissions ? now() : null,
                ]);

                // Recompute the cached totals from the ledger (the single source of
                // truth) rather than incrementing — see syncReferralTotals.
                $this->syncReferralTotals($referrer->id);

                if ($program->auto_approve_commissions) {
                    $this->notifySafely(fn () => app(NotificationEventService::class)->referralEarned($commission));
                }

                return $commission;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // A concurrent activation already created this order's commission — the
            // unique(order_id) index is the source of truth, so treat this as a no-op.
            return null;
        }
    }

    public function awardReferralCreditsForPayment(Payment $payment): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $payment->loadMissing(['user', 'plan']);
        $user = $payment->user;
        $program = $this->program();

        if (
            ! $user
            || ! $user->referred_by
            || ! $program->referral_credits_enabled
            || (float) $program->referral_credits_amount <= 0
            || $this->hasEarlierCompletedPayment($payment)
        ) {
            return;
        }

        $referrer = User::find($user->referred_by);

        if (! $referrer || $referrer->affiliate_banned) {
            return;
        }

        $alreadyAwarded = CreditTransaction::query()
            ->where('user_id', $referrer->id)
            ->where('type', 'referral')
            ->where('meta->payment_id', $payment->id)
            ->where('meta->reward', 'first_purchase_referral_credit')
            ->exists();

        if ($alreadyAwarded) {
            return;
        }

        $referrer->addCredits(
            (float) $program->referral_credits_amount,
            'referral',
            translate('First purchase referral credits'),
            [
                'reward' => 'first_purchase_referral_credit',
                'payment_id' => $payment->id,
                'payment_ulid' => $payment->ulid,
                'referred_user_id' => $user->id,
            ]
        );

        app(NotificationEventService::class)->referralCreditsEarned(
            $referrer,
            (float) $program->referral_credits_amount
        );
    }

    public function approveCommission(AffiliateCommission $commission): void
    {
        DB::transaction(function () use ($commission) {
            // Lock + re-check inside the transaction so concurrent approvals of the
            // same commission cannot both credit referral_earnings (which would
            // inflate the withdrawable balance).
            $locked = AffiliateCommission::lockForUpdate()->find($commission->id);

            if (! $locked || $locked->status !== 'pending') {
                return;
            }

            $locked->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $this->syncReferralTotals($locked->referrer_id);
            $this->notifySafely(fn () => app(NotificationEventService::class)->referralEarned($locked->fresh('referrer')));
        });
    }

    /**
     * Reject a commission and reverse any earnings/counters that were already
     * credited (auto-approval or prior manual approval).
     */
    public function rejectCommission(AffiliateCommission $commission): void
    {
        DB::transaction(function () use ($commission) {
            // Lock + re-check inside the transaction so a double-reject (or a
            // reject racing an approve) cannot claw back earnings twice.
            $locked = AffiliateCommission::lockForUpdate()->find($commission->id);

            if (! $locked || in_array($locked->status, ['rejected', 'cancelled'], true)) {
                return;
            }

            $locked->update([
                'status' => 'rejected',
                'approved_at' => null,
            ]);

            // Recompute the cached totals from the ledger. This also self-heals the
            // old drift where rejecting a PENDING commission left the count inflated.
            $this->syncReferralTotals($locked->referrer_id);

            $this->notifySafely(fn () => app(NotificationEventService::class)->commissionRejected($locked->fresh('referrer')));
        });
    }

    /**
     * Reverse affiliate rewards when the underlying payment is refunded/cancelled.
     * Pending/approved commissions are rejected and any credited earnings clawed
     * back; already-PAID commissions are left untouched because the payout money
     * already left the platform (an admin must handle those manually). Also
     * reverses any first-purchase referral wallet credits granted for the order.
     */
    public function clawbackCommissionForPayment(Payment $payment): void
    {
        $commissions = AffiliateCommission::query()
            ->where('order_id', $payment->id)
            ->whereIn('status', ['pending', 'approved'])
            ->get();

        foreach ($commissions as $commission) {
            $this->rejectCommission($commission);
        }

        $this->clawbackReferralCreditsForPayment($payment);
    }

    /**
     * Reverse first-purchase referral credits granted for a payment that was later
     * refunded — otherwise a referred user could buy, hand their referrer wallet
     * credits, then refund and keep them. Claws back only what is still available
     * (never drives the wallet negative), logs an audit transaction, and is
     * idempotent per payment.
     */
    protected function clawbackReferralCreditsForPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $grant = CreditTransaction::query()
                ->where('type', 'referral')
                ->where('meta->payment_id', $payment->id)
                ->where('meta->reward', 'first_purchase_referral_credit')
                ->first();

            if (! $grant) {
                return;
            }

            // Idempotency: a prior clawback tags the audit row with this reward key.
            $alreadyReversed = CreditTransaction::query()
                ->where('meta->payment_id', $payment->id)
                ->where('meta->reward', 'first_purchase_referral_credit_reversed')
                ->exists();

            if ($alreadyReversed) {
                return;
            }

            $referrer = User::query()->whereKey($grant->user_id)->lockForUpdate()->first();

            if (! $referrer) {
                return;
            }

            // Claw back at most the current balance so the wallet never goes negative.
            $reversible = min((float) $grant->amount, (float) $referrer->credits);

            if ($reversible > 0) {
                $referrer->decrement('credits', $reversible);
            }

            $referrer->creditTransactions()->create([
                'amount' => -$reversible,
                'balance_after' => (float) $referrer->fresh()->credits,
                'type' => 'admin_adjust',
                'description' => translate('Referral credits reversed (order refunded)'),
                'meta' => [
                    'reward' => 'first_purchase_referral_credit_reversed',
                    'payment_id' => $payment->id,
                    'granted_amount' => (float) $grant->amount,
                    'reversed_amount' => $reversible,
                ],
            ]);
        });
    }

    /**
     * Recompute a referrer's cached referral_earnings + referral_count directly from
     * the commission ledger — the single source of truth — instead of maintaining
     * them with increment/decrement (which drifted, e.g. a rejected PENDING commission
     * left the count inflated, and paid-out commissions could desync earnings).
     *
     * Both reflect commissions that actually earned: status approved or paid. This is
     * the LIFETIME gross earned; the withdrawable figure is availableBalance(), which
     * derives from the same ledger minus payouts, so the two can never disagree.
     */
    public function syncReferralTotals(int $referrerId): void
    {
        $totals = AffiliateCommission::query()
            ->where('referrer_id', $referrerId)
            ->whereIn('status', ['approved', 'paid'])
            ->selectRaw('COALESCE(SUM(amount), 0) as earnings, COUNT(*) as cnt')
            ->first();

        User::whereKey($referrerId)->update([
            'referral_earnings' => round((float) ($totals->earnings ?? 0), 4),
            'referral_count' => (int) ($totals->cnt ?? 0),
        ]);
    }

    /**
     * Mark approved commissions as `paid` for a processed payout, oldest first,
     * until the payout amount is exhausted. Commissions larger than the
     * remaining amount are skipped (not used to stop the whole loop).
     *
     * @return AffiliateCommission[] The commissions moved to `paid`.
     */
    public function markCommissionsPaid(int $referrerId, float $payoutAmount, int $holdDays): array
    {
        $remaining = $payoutAmount;
        $paid = [];

        AffiliateCommission::query()
            ->where('referrer_id', $referrerId)
            ->where('status', 'approved')
            ->where('approved_at', '<=', now()->subDays($holdDays))
            ->oldest('approved_at')
            ->get()
            ->each(function (AffiliateCommission $commission) use (&$remaining, &$paid) {
                if ($remaining <= 0) {
                    return false;
                }

                $commissionAmount = (float) $commission->amount;

                if ($commissionAmount > $remaining) {
                    return true; // skip this commission, try the next smaller one
                }

                $remaining -= $commissionAmount;
                $commission->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                $paid[] = $commission;

                return true;
            });

        return $paid;
    }

    public function availableBalance(User $user): float
    {
        $holdDays = (int) $this->program()->commission_hold_days;

        // Total earned = approved + paid commissions that have passed the hold
        // period. Paid commissions are included because they were earned and
        // their corresponding payout is subtracted below — this keeps the
        // ledger balanced regardless of which individual commissions a payout
        // was matched against.
        $earned = AffiliateCommission::query()
            ->where('referrer_id', $user->id)
            ->whereIn('status', ['approved', 'paid'])
            ->where('approved_at', '<=', now()->subDays($holdDays))
            ->sum('amount');

        // Everything already withdrawn or in flight reduces the balance.
        // Rejected payouts are excluded (the funds return to the affiliate).
        $withdrawnOrPending = $user->affiliatePayouts()
            ->whereIn('status', ['pending', 'processing', 'paid'])
            ->sum('amount');

        return max(0, round((float) $earned - (float) $withdrawnOrPending, 2));
    }

    public function findReferrer(string $codeOrSlug): ?User
    {
        return User::query()
            ->where('referral_code', $codeOrSlug)
            ->orWhere('affiliate_custom_slug', $codeOrSlug)
            ->first();
    }

    protected function calculateCommissionAmount(float $paymentAmount, AffiliateProgram $program): float
    {
        if ($program->commission_type === 'fixed') {
            return round((float) $program->commission_value, 4);
        }

        return round(($paymentAmount * (float) $program->commission_value) / 100, 4);
    }

    /**
     * The payment's amount converted to the store base currency. Commissions,
     * balances, and payouts are all denominated in the base currency, so an order
     * charged in a foreign per-country currency must be converted first.
     */
    protected function orderAmountInBaseCurrency(Payment $payment): float
    {
        $amount = (float) $payment->amount;
        $currency = $payment->currency ?: base_currency();

        if ($currency === base_currency()) {
            return $amount;
        }

        return round(convert_currency($amount, $currency, base_currency()), 4);
    }

    protected function hasEarlierCompletedPayment(Payment $payment): bool
    {
        return Payment::query()
            ->where('user_id', $payment->user_id)
            ->where('status', 'completed')
            ->where('id', '!=', $payment->id)
            ->exists();
    }

    /**
     * Run a notification/broadcast side-effect without ever letting it break the
     * surrounding financial transaction. A queued broadcast to a down/misconfigured
     * Pusher (common on QUEUE=sync self-hosted installs) must not roll back a
     * commission approval or credit grant — the money is what matters, the ping isn't.
     */
    protected function notifySafely(callable $notify): void
    {
        try {
            $notify();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Affiliate notification failed', ['error' => $e->getMessage()]);
        }
    }
}
