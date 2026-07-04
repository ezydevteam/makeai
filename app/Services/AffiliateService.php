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
        return (bool) settings('affiliate_enabled', false);
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

        $amount = $this->calculateCommissionAmount((float) $payment->amount, $program);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payment, $user, $referrer, $program, $amount) {
            $commission = AffiliateCommission::create([
                'referrer_id' => $referrer->id,
                'referred_id' => $user->id,
                'order_id' => $payment->id,
                'amount' => $amount,
                'status' => $program->auto_approve_commissions ? 'approved' : 'pending',
                'approved_at' => $program->auto_approve_commissions ? now() : null,
            ]);

            $referrer->increment('referral_count');

            if ($program->auto_approve_commissions) {
                $referrer->increment('referral_earnings', $amount);
                app(NotificationEventService::class)->referralEarned($commission);
            }

            return $commission;
        });
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

            User::whereKey($locked->referrer_id)->increment('referral_earnings', (float) $locked->amount);
            app(NotificationEventService::class)->referralEarned($locked->fresh('referrer'));
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

            $wasApproved = in_array($locked->status, ['approved', 'paid'], true);

            $locked->update([
                'status' => 'rejected',
                'approved_at' => null,
            ]);

            if ($wasApproved) {
                $referrer = User::find($locked->referrer_id);
                if ($referrer) {
                    $earnings = max(0, (float) $referrer->referral_earnings - (float) $locked->amount);
                    $count = max(0, (int) $referrer->referral_count - 1);
                    $referrer->forceFill([
                        'referral_earnings' => $earnings,
                        'referral_count' => $count,
                    ])->save();
                }
            }

            app(NotificationEventService::class)->commissionRejected($locked->fresh('referrer'));
        });
    }

    /**
     * Reverse a commission when the underlying payment is refunded/cancelled.
     * Paid commissions cannot be simply reversed (the money already left); they
     * are marked `cancelled` and earnings are clawed back so the referrer does
     * not keep rewards from a reversed order.
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

    protected function hasEarlierCompletedPayment(Payment $payment): bool
    {
        return Payment::query()
            ->where('user_id', $payment->user_id)
            ->where('status', 'completed')
            ->where('id', '!=', $payment->id)
            ->exists();
    }
}
