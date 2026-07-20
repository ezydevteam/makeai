<?php

namespace App\Services;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AiTool;
use App\Models\Comment;
use App\Models\Document;
use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\User;
use App\Models\ToolReview;
use App\Support\CountryCatalog;
use Illuminate\Support\Facades\Cache;
use App\Services\MailService;
use App\Jobs\SendAdminNotificationEmail;

class NotificationEventService
{
    public function __construct(
        private readonly InAppNotificationService $notifications,
        private readonly MailService $mail,
    ) {}

    public function creditsAdded(User $user, float $amount, string $reason = ''): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->notifications->send($user, [
            'title' => translate('Credits added'),
            'message' => translate(':amount credits added to your account.', [
                'amount' => $this->formatNumber($amount),
            ]),
            'level' => 'success',
            'category' => 'credits',
            'icon' => 'ti ti-coins',
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View dashboard'),
            'meta' => ['amount' => $amount, 'reason' => $reason],
        ]);

        $this->mail->send('credits_added', $user->email, [
            'user_name' => $user->name,
            'credits' => $this->formatNumber($amount),
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => url('/'),
        ]);
    }

    public function creditBalanceChanged(User $user, float $balance): void
    {
        $threshold = (float) settings('credits_low_threshold', 10);

        // The pricing/top-up page only exists when premium is available.
        $actionUrl = isProAvailable() ? route('pricing') : null;

        if ($balance <= 0) {
            $this->sendOnce("credits-exhausted:{$user->id}:".now()->toDateString(), function () use ($user, $actionUrl) {
                $this->notifications->send($user, [
                    'title' => translate('Credits exhausted'),
                    'message' => $actionUrl
                        ? translate("You've run out of credits. Top up to continue.")
                        : translate("You've run out of credits."),
                    'level' => 'error',
                    'category' => 'credits',
                    'icon' => 'ti ti-credit-card-off',
                    'action_url' => $actionUrl,
                    'action_label' => $actionUrl ? translate('Top up') : null,
                ]);
            });

            return;
        }

        if ($threshold > 0 && $balance <= $threshold) {
            $this->sendOnce("credits-low:{$user->id}:".now()->toDateString(), function () use ($user, $balance, $actionUrl) {
                $this->notifications->send($user, [
                    'title' => translate('Credits running low'),
                    'message' => translate('Your credits are running low (:balance remaining).', [
                        'balance' => $this->formatNumber($balance),
                    ]),
                    'level' => 'warning',
                    'category' => 'credits',
                    'icon' => 'ti ti-credit-card',
                    'action_url' => $actionUrl,
                    'action_label' => $actionUrl ? translate('Add credits') : null,
                ]);

                $this->mail->send('credits_low', $user->email, [
                    'user_name' => $user->name,
                    'credits' => $this->formatNumber($balance),
                ]);
            });
        }
    }

    public function subscriptionStarted(GatewaySubscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user || ! isProAvailable()) {
            return;
        }

        $this->notifications->send($subscription->user, [
            'title' => translate('Subscription active'),
            'message' => translate('Welcome to :plan! Your subscription is active.', [
                'plan' => $subscription->plan?->name ?? translate('your plan'),
            ]),
            'level' => 'success',
            'category' => 'subscription',
            'icon' => 'ti ti-calendar-star',
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View dashboard'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);
    }

    public function subscriptionConfirmed(User $user, GatewaySubscription $subscription): void
    {
        if (! $user || ! isProAvailable()) {
            return;
        }

        $this->notifications->send($user, [
            'title' => translate('Subscription confirmed'),
            'message' => translate('Your :plan subscription is now active.', [
                'plan' => $subscription->plan?->name ?? translate('your plan'),
            ]),
            'level' => 'success',
            'category' => 'subscription',
            'icon' => 'ti ti-circle-check',
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View dashboard'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);
    }

    public function subscriptionCanceled(GatewaySubscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user || ! isProAvailable()) {
            return;
        }

        $this->notifications->send($subscription->user, [
            'title' => translate('Subscription canceled'),
            'message' => translate('Your :plan subscription was canceled. Access remains until the current period ends.', [
                'plan' => $subscription->plan?->name ?? translate('plan'),
            ]),
            'level' => 'warning',
            'category' => 'subscription',
            'icon' => 'ti ti-calendar-cancel',
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View subscription'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);

        $this->mail->send('subscription_canceled', $subscription->user->email, [
            'user_name' => $subscription->user->name,
            'plan_name' => $subscription->plan?->name ?? translate('your plan'),
        ]);
    }

    /**
     * Fired when an administrator ends a user's subscription immediately (access
     * removed now). An optional reason is shown in-app.
     */
    public function subscriptionEndedByAdmin(GatewaySubscription $subscription, ?string $reason = null): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user) {
            return;
        }

        $planName = $subscription->plan?->name ?? translate('your plan');
        $message = translate('Your :plan subscription was ended by an administrator and access has been removed.', ['plan' => $planName]);

        if ($reason = trim((string) $reason)) {
            $message .= ' '.translate('Reason: :reason', ['reason' => $reason]);
        }

        $this->notifications->send($subscription->user, [
            'title' => translate('Subscription ended'),
            'message' => $message,
            'level' => 'warning',
            'category' => 'subscription',
            'icon' => 'ti ti-calendar-off',
            'action_url' => route('pricing'),
            'action_label' => translate('View plans'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);

        // $reason is the trimmed value (or '') after the block above. Pass a
        // ready-made line so the template reads cleanly when no reason was given.
        $this->mail->send('subscription_ended', $subscription->user->email, [
            'user_name' => $subscription->user->name,
            'plan_name' => $planName,
            'reason' => $reason ? translate('Reason: :reason', ['reason' => $reason]) : '',
        ]);
    }

    public function subscriptionExpired(GatewaySubscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user || ! isProAvailable()) {
            return;
        }

        $this->sendOnce("subscription-expired:{$subscription->id}", function () use ($subscription) {
            $this->notifications->send($subscription->user, [
                'title' => translate('Subscription expired'),
                'message' => translate('Your :plan subscription has expired.', [
                    'plan' => $subscription->plan?->name ?? translate('plan'),
                ]),
                'level' => 'warning',
                'category' => 'subscription',
                'icon' => 'ti ti-calendar-x',
                'action_url' => route('pricing'),
                'action_label' => translate('Renew'),
                'meta' => ['subscription_id' => $subscription->id],
            ]);

            $this->mail->send('subscription_expired', $subscription->user->email, [
                'user_name' => $subscription->user->name,
                'plan_name' => $subscription->plan?->name ?? translate('your plan'),
            ]);
        });
    }

    public function subscriptionRenewed(GatewaySubscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user || ! isProAvailable()) {
            return;
        }

        $this->notifications->send($subscription->user, [
            'title' => translate('Subscription renewed'),
            'message' => translate('Your :plan subscription has been renewed.', [
                'plan' => $subscription->plan?->name ?? translate('plan'),
            ]),
            'level' => 'success',
            'category' => 'subscription',
            'icon' => 'ti ti-calendar-share',
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View subscription'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);

        $this->mail->send('subscription_renewed', $subscription->user->email, [
            'user_name' => $subscription->user->name,
            'plan_name' => $subscription->plan?->name ?? translate('your plan'),
        ]);
    }

    public function trialStarted(GatewaySubscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user) {
            return;
        }

        $this->notifications->send($subscription->user, [
            'title' => translate('Trial started'),
            'message' => translate('Your :plan trial has started. Enjoy full access.', [
                'plan' => $subscription->plan?->name ?? translate('plan'),
            ]),
            'level' => 'success',
            'category' => 'subscription',
            'icon' => 'ti ti-calendar-time',
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View dashboard'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);

        $this->mail->send('subscription_trial_started', $subscription->user->email, [
            'user_name' => $subscription->user->name,
            'plan_name' => $subscription->plan?->name ?? translate('your plan'),
        ]);
    }

    /**
     * Fired when a paid trial period ends (distinct from a paid subscription
     * lapsing). Triggered from SubscriptionLifecycleService::expirePastDue when
     * the expiring subscription was still in the trialing state.
     */
    public function trialEnded(GatewaySubscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user) {
            return;
        }

        $this->sendOnce("trial-ended:{$subscription->id}", function () use ($subscription) {
            $this->notifications->send($subscription->user, [
                'title' => translate('Trial ended'),
                'message' => translate('Your :plan trial has ended.', [
                    'plan' => $subscription->plan?->name ?? translate('plan'),
                ]),
                'level' => 'warning',
                'category' => 'subscription',
                'icon' => 'ti ti-calendar-x',
                'action_url' => route('pricing'),
                'action_label' => translate('Upgrade'),
                'meta' => ['subscription_id' => $subscription->id],
            ]);

            $this->mail->send('subscription_trial_ended', $subscription->user->email, [
                'user_name' => $subscription->user->name,
                'plan_name' => $subscription->plan?->name ?? translate('your plan'),
            ]);
        });
    }

    /**
     * Fired when a user switches to a different plan (new checkout while an
     * active/trialing subscription already exists). Upgrade vs downgrade is
     * decided by the normalized monthly price of the two plans.
     */
    public function planChanged(User $user, ?Plan $oldPlan, ?Plan $newPlan, GatewaySubscription $subscription): void
    {
        if (! $user || ! isProAvailable()) {
            return;
        }

        $isUpgrade = (float) ($newPlan?->price_monthly ?? 0) >= (float) ($oldPlan?->price_monthly ?? 0);
        $planName = $newPlan?->name ?? $subscription->plan?->name ?? translate('your plan');

        $this->notifications->send($user, [
            'title' => $isUpgrade ? translate('Plan upgraded') : translate('Plan changed'),
            'message' => translate('Your subscription is now on the :plan plan.', ['plan' => $planName]),
            'level' => 'success',
            'category' => 'subscription',
            'icon' => 'ti ti-refresh',
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View subscription'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);

        $this->mail->send($isUpgrade ? 'subscription_upgraded' : 'subscription_downgraded', $user->email, [
            'user_name' => $user->name,
            'plan_name' => $planName,
        ]);
    }

    /**
     * Fired when a user schedules a downgrade to a lower plan that takes effect
     * at the end of the current period. Sends the confirmation email now; the
     * apply step later only posts an in-app note.
     */
    public function subscriptionDowngradeScheduled(GatewaySubscription $subscription, Plan $target, \Illuminate\Support\Carbon $effectiveAt): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user) {
            return;
        }

        $planName = $target->name;
        $dateLabel = $effectiveAt->toFormattedDateString();

        $this->notifications->send($subscription->user, [
            'title' => translate('Downgrade scheduled'),
            'message' => translate('You will move to the :plan plan on :date. Your current features stay active until then.', [
                'plan' => $planName,
                'date' => $dateLabel,
            ]),
            'level' => 'info',
            'category' => 'subscription',
            'icon' => 'ti ti-calendar-minus',
            'action_url' => route('user.dashboard.billing'),
            'action_label' => translate('Manage subscription'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);

        $this->mail->send('subscription_downgraded', $subscription->user->email, [
            'user_name' => $subscription->user->name,
            'plan_name' => $planName,
        ]);
    }

    /**
     * In-app note when a previously scheduled downgrade actually takes effect.
     */
    public function subscriptionDowngradeApplied(GatewaySubscription $subscription, ?Plan $oldPlan = null): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user) {
            return;
        }

        $this->notifications->send($subscription->user, [
            'title' => translate('Plan changed'),
            'message' => translate('Your subscription is now on the :plan plan.', [
                'plan' => $subscription->plan?->name ?? translate('your plan'),
            ]),
            'level' => 'info',
            'category' => 'subscription',
            'icon' => 'ti ti-arrow-down-circle',
            'action_url' => route('user.dashboard.billing'),
            'action_label' => translate('View subscription'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);
    }

    public function paymentSuccessful(Payment $payment, bool $suppressStartedEmail = false): void
    {
        $payment->loadMissing(['user', 'plan']);

        if (! $payment->user) {
            return;
        }

        $amount = format_currency((float) $payment->amount, $payment->currency);
        $this->notifications->send($payment->user, [
            'title' => translate('Payment received'),
            'message' => translate('Payment of :amount received. Invoice: #:id', [
                'amount' => $amount,
                'id' => $payment->ulid,
            ]),
            'level' => 'success',
            'category' => 'payment',
            'icon' => 'ti ti-receipt',
            'action_url' => route('checkout.pending', $payment),
            'action_label' => translate('View payment'),
            'meta' => ['payment_ulid' => $payment->ulid],
        ]);

        $this->notifications->notifyAdmins([
            'title' => translate('New payment received'),
            'message' => translate('New payment: :amount from :user', [
                'amount' => $amount,
                'user' => $payment->user->name,
            ]),
            'level' => 'success',
            'category' => 'payment',
            'icon' => 'ti ti-receipt',
            'action_url' => route('admin.users.show', $payment->user),
            'action_label' => translate('View user'),
            'meta' => ['payment_ulid' => $payment->ulid],
        ]);

        if ($payment->type === 'subscription') {
            $this->mail->send('invoice_paid', $payment->user->email, [
                'user_name' => $payment->user->name,
                'plan_name' => $payment->plan?->name ?? translate('your plan'),
                'amount' => $amount,
                'id' => $payment->ulid,
                'site_name' => settings('app_name', 'MakeAI'),
                'site_url' => url('/'),
            ]);

            // On a plan change the "welcome, your subscription has started" email
            // is misleading — the receipt (invoice_paid) plus the upgrade/downgrade
            // notice cover it. Only send it for genuinely new subscriptions.
            if (! $suppressStartedEmail) {
                $this->mail->send('subscription_started', $payment->user->email, [
                    'user_name' => $payment->user->name,
                    'plan_name' => $payment->plan?->name ?? translate('your plan'),
                    'site_name' => settings('app_name', 'MakeAI'),
                    'site_url' => url('/'),
                ]);
            }
        }
    }

    public function paymentFailed(Payment $payment, ?string $reason = null): void
    {
        $payment->loadMissing(['user', 'plan']);

        if (! $payment->user) {
            return;
        }

        $this->notifications->send($payment->user, [
            'title' => translate('Payment failed'),
            'message' => translate('Payment failed for your subscription. Please update billing.'),
            'level' => 'error',
            'category' => 'payment',
            'icon' => 'ti ti-credit-card-off',
            'action_url' => route('pricing'),
            'action_label' => translate('Update billing'),
            'meta' => ['payment_ulid' => $payment->ulid, 'reason' => $reason],
        ]);

        $this->notifications->notifyAdmins([
            'title' => translate('Payment failed'),
            'message' => translate('Payment failed: :user', ['user' => $payment->user->name]),
            'level' => 'error',
            'category' => 'payment',
            'icon' => 'ti ti-credit-card-off',
            'action_url' => route('admin.users.show', $payment->user),
            'action_label' => translate('View user'),
            'meta' => ['payment_ulid' => $payment->ulid, 'reason' => $reason],
        ]);

        $this->mail->send('subscription_payment_failed', $payment->user->email, [
            'user_name' => $payment->user->name,
            'plan_name' => $payment->plan?->name ?? translate('your plan'),
        ]);
    }

    /**
     * A pending payment was manually rejected by an administrator
     * (e.g. bank transfer proof could not be verified).
     */
    public function paymentRejected(Payment $payment, ?string $reason = null): void
    {
        $payment->loadMissing(['user', 'plan']);

        if (! $payment->user) {
            return;
        }

        $amount = format_currency((float) $payment->amount, $payment->currency);

        $message = $reason
            ? translate('Your payment of :amount (#:id) was rejected: :reason', [
                'amount' => $amount,
                'id' => $payment->ulid,
                'reason' => $reason,
            ])
            : translate('Your payment of :amount (#:id) was rejected. Please contact support if you believe this is a mistake.', [
                'amount' => $amount,
                'id' => $payment->ulid,
            ]);

        $this->notifications->send($payment->user, [
            'title' => translate('Payment rejected'),
            'message' => $message,
            'level' => 'error',
            'category' => 'payment',
            'icon' => 'ti ti-receipt-off',
            'action_url' => route('checkout.pending', $payment),
            'action_label' => translate('View payment'),
            'meta' => ['payment_ulid' => $payment->ulid, 'reason' => $reason],
        ]);

        SendAdminNotificationEmail::dispatch($payment->user_id, [
            'title' => translate('Payment rejected'),
            'message' => $message,
            'action_url' => route('checkout.pending', $payment),
            'action_label' => translate('View payment'),
        ]);
    }

    public function transactionPending(Payment $payment): void
    {
        $payment->loadMissing(['user', 'plan']);

        if (! $payment->user) {
            return;
        }

        $this->sendOnce("transaction-pending:{$payment->id}", function () use ($payment) {
            $this->notifications->notifyAdmins([
                'title' => translate('New transaction pending'),
                'message' => translate('New transaction pending by :user, trx id: :trx_id', [
                    'user' => $payment->user->name,
                    'trx_id' => $payment->gateway_payment_id ?: $payment->ulid,
                ]),
                'level' => 'warning',
                'category' => 'payment',
                'icon' => 'ti ti-clock-hour-4',
                'action_url' => route('admin.users.show', $payment->user),
                'action_label' => translate('View user'),
                'meta' => [
                    'payment_ulid' => $payment->ulid,
                    'gateway' => $payment->gateway,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                ],
            ]);
        });
    }

    public function referralEarned(AffiliateCommission $commission): void
    {
        $commission->loadMissing('referrer');

        if (! $commission->referrer) {
            return;
        }

        $amount = format_currency((float) $commission->amount);

        $this->notifications->send($commission->referrer, [
            'title' => translate('Referral reward earned'),
            'message' => translate('You earned :amount from a referral!', ['amount' => $amount]),
            'level' => 'success',
            'category' => 'affiliate',
            'icon' => 'ti ti-users',
            'action_url' => route('user.dashboard.affiliate'),
            'action_label' => translate('View affiliate dashboard'),
            'meta' => ['commission_id' => $commission->id],
        ]);

        $this->mail->send('affiliate_commission_earned', $commission->referrer->email, [
            'user_name' => $commission->referrer->name,
            'amount' => $amount,
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => url('/'),
        ]);
    }

    /**
     * Fired when a referrer is granted account credits because a user they
     * referred made their first purchase (distinct from a cash commission).
     */
    public function referralCreditsEarned(User $referrer, float $amount): void
    {
        if (! $referrer) {
            return;
        }

        $this->notifications->send($referrer, [
            'title' => translate('Referral reward earned'),
            'message' => translate('You earned :amount referral credits!', [
                'amount' => $this->formatNumber($amount),
            ]),
            'level' => 'success',
            'category' => 'affiliate',
            'icon' => 'ti ti-users-plus',
            'action_url' => route('user.dashboard.affiliate'),
            'action_label' => translate('View affiliate dashboard'),
        ]);

        $this->mail->send('referral_earned', $referrer->email, [
            'user_name' => $referrer->name,
            'credits' => $this->formatNumber($amount),
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => route('user.dashboard.affiliate'),
        ]);
    }

    public function commissionRejected(AffiliateCommission $commission): void
    {
        $commission->loadMissing('referrer');

        if (! $commission->referrer) {
            return;
        }

        $amount = format_currency((float) $commission->amount);

        $this->notifications->send($commission->referrer, [
            'title' => translate('Commission rejected'),
            'message' => translate('A commission of :amount was rejected by the admin.', ['amount' => $amount]),
            'level' => 'warning',
            'category' => 'affiliate',
            'icon' => 'ti ti-user-x',
            'action_url' => route('user.dashboard.affiliate'),
            'action_label' => translate('View affiliate dashboard'),
            'meta' => ['commission_id' => $commission->id],
        ]);

        $this->mail->send('affiliate_commission_rejected', $commission->referrer->email, [
            'user_name' => $commission->referrer->name,
            'amount' => $amount,
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => url('/'),
        ]);
    }

    public function payoutRequested(AffiliatePayout $payout): void
    {
        $payout->loadMissing('user');

        if (! $payout->user) {
            return;
        }

        $amount = format_currency((float) $payout->amount);

        $this->notifications->notifyAdminsWithPermission('payments.gateways', [
            'title' => translate('Payout request'),
            'message' => translate('You have a new payout request: :amount', ['amount' => $amount]),
            'level' => 'info',
            'category' => 'affiliate',
            'icon' => 'ti ti-cash',
            'action_url' => route('admin.affiliate.index'),
            'action_label' => translate('Review payout'),
            'meta' => [
                'payout_id' => $payout->id,
                'user_ulid' => $payout->user->ulid,
                'method' => $payout->method,
            ],
        ]);
    }

    public function payoutProcessing(AffiliatePayout $payout): void
    {
        $payout->loadMissing('user');

        if (! $payout->user) {
            return;
        }

        $amount = format_currency((float) $payout->amount);

        $this->notifications->send($payout->user, [
            'title' => translate('Payout in progress'),
            'message' => translate('Your payout request of :amount is being processed.', ['amount' => $amount]),
            'level' => 'info',
            'category' => 'affiliate',
            'icon' => 'ti ti-refresh',
            'action_url' => route('user.dashboard.affiliate'),
            'action_label' => translate('View affiliate dashboard'),
            'meta' => ['payout_id' => $payout->id],
        ]);
    }

    public function payoutApproved(AffiliatePayout $payout): void
    {
        $payout->loadMissing('user');

        if (! $payout->user) {
            return;
        }

        $amount = format_currency((float) $payout->amount);

        $this->notifications->send($payout->user, [
            'title' => translate('Payout paid'),
            'message' => translate('Your payout request of :amount has been paid.', ['amount' => $amount]),
            'level' => 'success',
            'category' => 'affiliate',
            'icon' => 'ti ti-circle-check',
            'action_url' => route('user.dashboard.affiliate'),
            'action_label' => translate('View affiliate dashboard'),
            'meta' => ['payout_id' => $payout->id],
        ]);

        $this->mail->send('affiliate_payout_paid', $payout->user->email, [
            'user_name' => $payout->user->name,
            'amount' => $amount,
            'method' => $payout->method,
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => url('/'),
        ]);
    }

    public function payoutCancelled(AffiliatePayout $payout): void
    {
        $payout->loadMissing('user');

        if (! $payout->user) {
            return;
        }

        $amount = format_currency((float) $payout->amount);

        $this->notifications->send($payout->user, [
            'title' => translate('Payout rejected'),
            'message' => translate('Your payout request of :amount was rejected.', ['amount' => $amount]),
            'level' => 'warning',
            'category' => 'affiliate',
            'icon' => 'ti ti-circle-x',
            'action_url' => route('user.dashboard.affiliate'),
            'action_label' => translate('View affiliate dashboard'),
            'meta' => ['payout_id' => $payout->id],
        ]);

        $this->mail->send('affiliate_payout_rejected', $payout->user->email, [
            'user_name' => $payout->user->name,
            'amount' => $amount,
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => url('/'),
        ]);
    }

    public function documentReady(Document $document): void
    {
        $document->loadMissing('user');

        if (! $document->user) {
            return;
        }

        $this->notifications->send($document->user, [
            'title' => translate('Document ready'),
            'message' => translate("Your document ':name' has been processed.", ['name' => $document->title]),
            'level' => 'success',
            'category' => 'document',
            'icon' => 'ti ti-file-text',
            'action_url' => route('documents.edit', $document),
            'action_label' => translate('Open document'),
            'meta' => ['document_id' => $document->id],
        ]);
    }

    public function mediaReady(User $user, string $type, ?string $url = null): void
    {
        $icon = 'ti ti-photo';
        if (str_contains(strtolower($type), 'voice') || str_contains(strtolower($type), 'audio')) {
            $icon = 'ti ti-music';
        } elseif (str_contains(strtolower($type), 'video')) {
            $icon = 'ti ti-video';
        }

        $this->notifications->send($user, [
            'title' => translate('Media ready'),
            'message' => translate('Your :type is ready to view.', ['type' => $type]),
            'level' => 'success',
            'category' => 'media',
            'icon' => $icon,
            'action_url' => $url,
            'action_label' => $url ? translate('View') : null,
            'meta' => ['type' => $type],
        ]);
    }

    /**
     * A gateway webhook failed permanently, after every retry.
     *
     * This is the "we may have taken their money and given them nothing" case: the gateway
     * charged the customer but activation never completed. It used to be written to the log
     * and nowhere else, so nobody found out until the customer complained. An admin must be
     * told so they can activate the payment by hand.
     */
    public function webhookProcessingFailed(string $gateway, string $error, ?string $paymentUlid = null): void
    {
        $this->notifications->notifyAdmins([
            'title' => translate('Payment webhook failed'),
            'message' => translate('A :gateway webhook could not be processed after retries. A customer may have paid without receiving access. Check the transaction and activate it manually if needed.', [
                'gateway' => ucfirst($gateway),
            ]),
            'level' => 'danger',
            'category' => 'payment',
            'icon' => 'ti ti-alert-triangle',
            'action_url' => route('admin.transactions.index'),
            'action_label' => translate('View transactions'),
            'meta' => [
                'gateway' => $gateway,
                'payment_ulid' => $paymentUlid,
                'error' => $error,
            ],
        ]);
    }

    public function adminAnnouncement(string $title, string $message, string $audience = 'all', ?string $url = null): int
    {
        $sent = 0;
        $queue = settings('notifications_driver', 'polling') !== 'polling';

        User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            ->when($audience === 'free', function ($query) {
                $query->where(function ($free) {
                    $free->whereNull('subscription_status')
                        ->orWhereNotIn('subscription_status', ['active', 'trialing']);
                });
            })
            ->when($audience === 'pro', fn ($query) => $query->whereIn('subscription_status', ['active', 'trialing']))
            ->chunkById(100, function ($users) use (&$sent, $title, $message, $url, $queue) {
                foreach ($users as $user) {
                    $this->notifications->send($user, [
                        'title' => $title,
                        'message' => $message,
                        'level' => 'info',
                        'category' => 'announcement',
                        'icon' => 'ti ti-bell',
                        'action_url' => $url,
                        'action_label' => $url ? translate('View') : null,
                    ], $queue);
                    $sent++;
                }
            });

        return $sent;
    }

    public function newToolLaunched(AiTool $tool): int
    {
        if (! $tool->is_active) {
            return 0;
        }

        $sent = 0;
        $queue = settings('notifications_driver', 'polling') !== 'polling';

        if (! Cache::add("notification-once:tool-launched:{$tool->id}", true, now()->addYears(5))) {
            return 0;
        }

        User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            ->chunkById(100, function ($users) use (&$sent, $tool, $queue) {
                foreach ($users as $user) {
                    $this->notifications->send($user, [
                        'title' => translate('New tool launched'),
                        'message' => translate('New tool launched: :tool', ['tool' => $tool->name]),
                        'level' => 'info',
                        'category' => 'ai_tool',
                        'icon' => 'ti ti-rocket',
                        'action_url' => route('ai.tools.show', $tool->slug),
                        'action_label' => translate('Try now'),
                        'meta' => [
                            'tool_id' => $tool->id,
                            'tool_slug' => $tool->slug,
                        ],
                    ], $queue);
                    $sent++;
                }
            });

        return $sent;
    }

    public function passwordChanged(User $user): void
    {
        $this->notifications->send($user, [
            'title' => translate('Password changed'),
            'message' => translate("Your password was changed. Wasn't you? Secure your account."),
            'level' => 'warning',
            'category' => 'security',
            'icon' => 'ti ti-lock-password',
            'action_url' => route('password.request'),
            'action_label' => translate('Secure account'),
        ]);
    }

    public function newLogin(User $user, string $ip, ?string $city = null, ?string $country = null): void
    {
        $city = $this->locationPart($city, translate('Unknown city'));
        $country = $this->countryName($country);

        $this->notifications->send($user, [
            'title' => translate('New login detected'),
            'message' => translate('🔐 New login detected from :city, :country', [
                'city' => $city,
                'country' => $country,
            ]),
            'level' => 'warning',
            'category' => 'security',
            'icon' => 'ti ti-shield-lock',
            'action_url' => route('user.dashboard'),
            'action_label' => translate('Review account'),
            'meta' => ['ip' => $ip, 'city' => $city, 'country' => $country],
        ]);

        $this->notifications->notifyAdmins([
            'title' => translate('New login from new device'),
            'message' => translate('🔐 New login detected from :city, :country', [
                'city' => $city,
                'country' => $country,
            ]),
            'level' => 'warning',
            'category' => 'security',
            'icon' => 'ti ti-shield-lock',
            'action_url' => route('admin.users.show', $user),
            'action_label' => translate('View user'),
            'meta' => ['ip' => $ip, 'user_ulid' => $user->ulid],
        ]);
    }

    public function newUserRegistered(User $user): void
    {
        // Welcome the new user (covers standard + social signup).
        $this->mail->send('welcome', $user->email, [
            'user_name' => $user->name,
        ]);

        $this->notifications->notifyAdmins([
            'title' => translate('New user registered'),
            'message' => translate(':name (:email) created an account.', [
                'name' => $user->name,
                'email' => $user->email,
            ]),
            'level' => 'info',
            'category' => 'users',
            'icon' => 'ti ti-user-plus',
            'action_url' => route('admin.users.show', $user),
            'action_label' => translate('View user'),
        ]);
    }

    public function newCommentPending(Comment $comment): void
    {
        $this->notifications->notifyAdminsWithPermission('content.comments', [
            'title' => translate('New comment pending'),
            'message' => translate('New comment pending moderation.'),
            'level' => 'info',
            'category' => 'comments',
            'icon' => 'ti ti-message-2',
            'action_url' => route('admin.comments.index'),
            'action_label' => translate('Moderate comments'),
            'meta' => ['comment_id' => $comment->id],
        ]);
    }

    /**
     * A pending comment was approved by a moderator. Comments can be left by
     * guests, so the email goes to the account address when there is one and to
     * the guest address otherwise; the in-app note is only possible for members.
     */
    public function commentApproved(Comment $comment): void
    {
        $comment->loadMissing(['user', 'commentable']);

        $title = $this->commentableTitle($comment);
        $url = $this->commentableUrl($comment);

        if ($comment->user) {
            $this->notifications->send($comment->user, [
                'title' => translate('Comment approved'),
                'message' => translate('Your comment on :title is now live.', ['title' => $title]),
                'level' => 'success',
                'category' => 'comments',
                'icon' => 'ti ti-message-check',
                'action_url' => $url,
                'action_label' => $url ? translate('View comment') : null,
                'meta' => ['comment_id' => $comment->id],
            ]);
        }

        $recipient = $comment->user?->email ?: $comment->guest_email;

        if (! $recipient) {
            return;
        }

        $this->mail->send('comment_approved', $recipient, [
            'user_name' => $comment->user?->name ?: ($comment->guest_name ?: translate('there')),
            'post_title' => $title,
            'comment_url' => $url ?? url('/'),
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => url('/'),
        ]);
    }

    /**
     * Comments are polymorphic (blog posts, pages, AI tools) and each lives on a
     * different public route, so the link has to be resolved per type. An unknown
     * or deleted commentable yields null and the email falls back to the homepage.
     */
    private function commentableUrl(Comment $comment): ?string
    {
        $commentable = $comment->commentable;

        if (! $commentable) {
            return null;
        }

        $anchor = '#comment-'.$comment->id;

        return match (true) {
            $commentable instanceof \App\Models\BlogPost => route('blog.show', $commentable->slug).$anchor,
            $commentable instanceof AiTool => route('ai.tools.show', $commentable->slug).$anchor,
            $commentable instanceof \App\Models\Page => route('page.show', $commentable->slug).$anchor,
            default => null,
        };
    }

    private function commentableTitle(Comment $comment): string
    {
        $commentable = $comment->commentable;

        return $commentable?->title
            ?? $commentable?->name
            ?? translate('a post');
    }

    public function licenseGracePeriod(int $days): void
    {
        $this->notifications->notifyAdmins([
            'title' => translate('License re-verification warning'),
            'message' => translate('License re-verification failed. :days days grace period remaining.', ['days' => $days]),
            'level' => 'warning',
            'category' => 'license',
            'icon' => 'ti ti-key',
        ], 'super-admin');
    }

    public function updateAvailable(string $version): void
    {
        $this->notifications->notifyAdmins([
            'title' => translate('Update available'),
            'message' => translate(':app v:version is available. Update now.', [
                'app' => settings('app_name', translate('Application')),
                'version' => $version,
            ]),
            'level' => 'info',
            'category' => 'system',
            'icon' => 'ti ti-download',
        ], 'super-admin');
    }

    public function serverHealthWarning(string $checkName): void
    {
        $this->notifications->notifyAdmins([
            'title' => translate('Server health warning'),
            'message' => translate('Health check failed: :check', ['check' => $checkName]),
            'level' => 'error',
            'category' => 'system',
            'icon' => 'ti ti-alert-triangle',
        ], 'super-admin');
    }

    public function highAiCostAlert(float $percentage): void
    {
        $bucket = (int) floor($percentage / 10) * 10;
        $this->sendOnce('high-ai-cost-alert:'.now()->toDateString().":{$bucket}", function () use ($percentage) {
            $this->notifications->notifyAdmins([
                'title' => translate('High AI cost alert'),
                'message' => translate('Daily AI spend reached :threshold% of budget.', [
                    'threshold' => round($percentage),
                ]),
                'level' => 'warning',
                'category' => 'ai',
                'icon' => 'ti ti-chart-bar',
                'action_url' => route('admin.ai.logs.index'),
                'action_label' => translate('View usage logs'),
            ], 'super-admin');
        });
    }

    public function notifySubscriptionsRenewingSoon(): int
    {
        if (! isProAvailable()) {
            return 0;
        }

        $count = 0;
        User::query()
            ->where('subscription_status', 'active')
            ->whereBetween('subscription_ends_at', [now()->addDays(3)->startOfDay(), now()->addDays(3)->endOfDay()])
            ->chunkById(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $this->notifications->send($user, [
                        'title' => translate('Subscription renewing soon'),
                        'message' => translate(':plan_name renews in 3 days. Make sure your payment method is up to date.', [
                            'plan_name' => $user->plan?->name ?? translate('Your plan'),
                        ]),
                        'level' => 'info',
                        'category' => 'billing',
                        'icon' => 'ti ti-calendar-event',
                        'action_url' => route('billing.portal'),
                        'action_label' => translate('Manage billing'),
                    ]);

                    $this->mail->send('subscription_expiring_soon', $user->email, [
                        'user_name' => $user->name,
                        'plan_name' => $user->plan?->name ?? translate('your plan'),
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Remind users whose free trial ends within the next ~2 days. sendOnce keys
     * each trial so a given trial only ever gets a single "ending soon" nudge.
     */
    public function notifyTrialsEndingSoon(): int
    {
        $count = 0;

        GatewaySubscription::query()
            ->where('status', GatewaySubscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(2)])
            ->with(['user', 'plan'])
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->user) {
                        continue;
                    }

                    $this->sendOnce("trial-ending:{$subscription->id}", function () use ($subscription, &$count) {
                        $this->notifications->send($subscription->user, [
                            'title' => translate('Trial ending soon'),
                            'message' => translate('Your :plan trial ends soon. Add a payment method to keep your access.', [
                                'plan' => $subscription->plan?->name ?? translate('plan'),
                            ]),
                            'level' => 'warning',
                            'category' => 'billing',
                            'icon' => 'ti ti-calendar-time',
                            'action_url' => route('pricing'),
                            'action_label' => translate('Choose a plan'),
                            'meta' => ['subscription_id' => $subscription->id],
                        ]);

                        $this->mail->send('subscription_trial_expiring', $subscription->user->email, [
                            'user_name' => $subscription->user->name,
                            'plan_name' => $subscription->plan?->name ?? translate('your plan'),
                        ]);

                        $count++;
                    });
                }
            });

        return $count;
    }

    public function newToolReviewSubmitted(ToolReview $review): void
    {
        $review->loadMissing('tool');
        $toolName = $review->tool?->name ?? $review->tool_slug;

        $this->notifications->notifyAdminsWithPermission('ai.tools', [
            'title' => translate('New tool review submitted'),
            'message' => translate('A new review has been submitted for :tool.', ['tool' => $toolName]),
            'level' => 'info',
            'category' => 'ai_tool',
            'icon' => 'ti ti-star',
            'action_url' => route('admin.ai.reviews.index'),
            'action_label' => translate('Moderate reviews'),
            'meta' => ['review_id' => $review->id],
        ]);
    }

    public function toolReviewReply(ToolReview $review): void
    {
        $review->loadMissing(['user', 'tool']);
        if (!$review->user) {
            return;
        }

        $toolName = $review->tool?->name ?? $review->tool_slug;

        $this->notifications->send($review->user, [
            'title' => translate('Tool review reply from admin'),
            'message' => translate('Admin has replied to your review on :tool.', ['tool' => $toolName]),
            'level' => 'info',
            'category' => 'ai_tool',
            'icon' => 'ti ti-message-dots',
            'action_url' => route('ai.tools.show', $review->tool_slug),
            'action_label' => translate('View review'),
            'meta' => ['review_id' => $review->id],
        ]);
    }

    public function toolReviewApproved(ToolReview $review): void
    {
        $review->loadMissing(['user', 'tool']);
        if (!$review->user) {
            return;
        }

        $toolName = $review->tool?->name ?? $review->tool_slug;

        $this->notifications->send($review->user, [
            'title' => translate('Tool review approved'),
            'message' => translate('Your review on :tool has been approved.', ['tool' => $toolName]),
            'level' => 'success',
            'category' => 'ai_tool',
            'icon' => 'ti ti-circle-check',
            'action_url' => route('ai.tools.show', $review->tool_slug),
            'action_label' => translate('View review'),
            'meta' => ['review_id' => $review->id],
        ]);

        if (! $review->user->email) {
            return;
        }

        $this->mail->send('tool_review_approved', $review->user->email, [
            'user_name' => $review->user->name,
            'tool_name' => $toolName,
            'review_url' => route('ai.tools.show', $review->tool_slug),
            'site_name' => settings('app_name', 'MakeAI'),
            'site_url' => url('/'),
        ]);
    }

    private function sendOnce(string $key, callable $callback): void
    {
        if (Cache::add('notification-once:'.$key, true, now()->addDays(7))) {
            $callback();
        }
    }

    private function formatNumber(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function countryName(?string $country): string
    {
        $country = $this->locationPart($country, translate('Unknown country'));

        return strlen($country) === 2
            ? (CountryCatalog::countryName($country) ?? strtoupper($country))
            : $country;
    }

    private function locationPart(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }
}
