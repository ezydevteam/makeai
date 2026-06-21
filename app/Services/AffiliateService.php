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

    public function captureVisit(Request $request, string $code): ?AffiliateReferral
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $referrer = $this->findReferrer($code);

        if (! $referrer) {
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

        if (! $referrer || $referrer->is($user)) {
            return;
        }

        DB::transaction(function () use ($request, $user, $referrer) {
            $user->forceFill(['referred_by' => $referrer->id])->save();

            $referral = AffiliateReferral::query()
                ->where('referrer_id', $referrer->id)
                ->whereNull('referred_id')
                ->where(function ($query) use ($request) {
                    $query->where('ip_address', $request->ip())
                        ->orWhere('referral_code', $request->session()->get('affiliate_ref'));
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

        if ($program->commission_on === 'first_purchase' && $this->hasEarlierCompletedPayment($payment)) {
            return null;
        }

        if (AffiliateCommission::where('order_id', $payment->id)->exists()) {
            return null;
        }

        $referrer = User::find($user->referred_by);

        if (! $referrer) {
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

        if (! $referrer) {
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
        if ($commission->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($commission) {
            $commission->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            User::whereKey($commission->referrer_id)->increment('referral_earnings', (float) $commission->amount);
            app(NotificationEventService::class)->referralEarned($commission->fresh('referrer'));
        });
    }

    public function availableBalance(User $user): float
    {
        $holdDays = (int) $this->program()->commission_hold_days;
        $approved = AffiliateCommission::query()
            ->where('referrer_id', $user->id)
            ->where('status', 'approved')
            ->where('approved_at', '<=', now()->subDays($holdDays))
            ->sum('amount');

        $requested = $user->affiliatePayouts()
            ->whereIn('status', ['pending', 'processing', 'paid'])
            ->sum('amount');

        return max(0, round((float) $approved - (float) $requested, 2));
    }

    public function findReferrer(string $codeOrSlug): ?User
    {
        return User::query()
            ->where('referral_code', $codeOrSlug)
            ->orWhere('affiliate_custom_slug', $codeOrSlug)
            ->first();
    }

    private function calculateCommissionAmount(float $paymentAmount, AffiliateProgram $program): float
    {
        if ($program->commission_type === 'fixed') {
            return round((float) $program->commission_value, 4);
        }

        return round(($paymentAmount * (float) $program->commission_value) / 100, 4);
    }

    private function hasEarlierCompletedPayment(Payment $payment): bool
    {
        return Payment::query()
            ->where('user_id', $payment->user_id)
            ->where('status', 'completed')
            ->where('id', '!=', $payment->id)
            ->exists();
    }
}
