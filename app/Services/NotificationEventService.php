<?php

namespace App\Services;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AiTemplate;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\CountryCatalog;
use Illuminate\Support\Facades\Cache;

class NotificationEventService
{
    public function __construct(private readonly InAppNotificationService $notifications) {}

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
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View dashboard'),
            'meta' => ['amount' => $amount, 'reason' => $reason],
        ]);
    }

    public function creditBalanceChanged(User $user, float $balance): void
    {
        $threshold = (float) settings('credits_low_threshold', 10);

        if ($balance <= 0) {
            $this->sendOnce("credits-exhausted:{$user->id}:".now()->toDateString(), function () use ($user) {
                $this->notifications->send($user, [
                    'title' => translate('Credits exhausted'),
                    'message' => translate("You've run out of credits. Top up to continue."),
                    'level' => 'error',
                    'category' => 'credits',
                    'action_url' => route('pricing'),
                    'action_label' => translate('Top up'),
                ]);
            });

            return;
        }

        if ($threshold > 0 && $balance <= $threshold) {
            $this->sendOnce("credits-low:{$user->id}:".now()->toDateString(), function () use ($user, $balance) {
                $this->notifications->send($user, [
                    'title' => translate('Credits running low'),
                    'message' => translate('Your credits are running low (:balance remaining).', [
                        'balance' => $this->formatNumber($balance),
                    ]),
                    'level' => 'warning',
                    'category' => 'credits',
                    'action_url' => route('pricing'),
                    'action_label' => translate('Add credits'),
                ]);
            });
        }
    }

    public function subscriptionStarted(Subscription $subscription): void
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
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View dashboard'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);
    }

    public function subscriptionRenewingSoon(Subscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);

        if (! $subscription->user || ! isProAvailable()) {
            return;
        }

        $days = max(1, now()->diffInDays($subscription->current_period_end, false));
        $this->sendOnce("subscription-renewing:{$subscription->id}:".$subscription->current_period_end?->toDateString(), function () use ($subscription, $days) {
            $this->notifications->send($subscription->user, [
                'title' => translate('Subscription renews soon'),
                'message' => translate('Your :plan subscription renews in :days days.', [
                    'plan' => $subscription->plan?->name ?? translate('plan'),
                    'days' => $days,
                ]),
                'level' => 'info',
                'category' => 'subscription',
                'action_url' => route('user.dashboard'),
                'action_label' => translate('View subscription'),
                'meta' => ['subscription_id' => $subscription->id],
            ]);
        });
    }

    public function subscriptionExpired(Subscription $subscription): void
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
                'action_url' => route('pricing'),
                'action_label' => translate('Renew'),
                'meta' => ['subscription_id' => $subscription->id],
            ]);
        });
    }

    public function subscriptionCanceled(Subscription $subscription): void
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
            'action_url' => route('user.dashboard'),
            'action_label' => translate('View subscription'),
            'meta' => ['subscription_id' => $subscription->id],
        ]);
    }

    public function paymentSuccessful(Payment $payment): void
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
            'action_url' => route('admin.users.show', $payment->user),
            'action_label' => translate('View user'),
            'meta' => ['payment_ulid' => $payment->ulid],
        ]);
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
            'action_url' => route('pricing'),
            'action_label' => translate('Update billing'),
            'meta' => ['payment_ulid' => $payment->ulid, 'reason' => $reason],
        ]);

        $this->notifications->notifyAdmins([
            'title' => translate('Payment failed'),
            'message' => translate('Payment failed: :user', ['user' => $payment->user->name]),
            'level' => 'error',
            'category' => 'payment',
            'action_url' => route('admin.users.show', $payment->user),
            'action_label' => translate('View user'),
            'meta' => ['payment_ulid' => $payment->ulid, 'reason' => $reason],
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

        $this->notifications->send($commission->referrer, [
            'title' => translate('Referral reward earned'),
            'message' => translate('You earned :amount from a referral!', [
                'amount' => format_currency((float) $commission->amount),
            ]),
            'level' => 'success',
            'category' => 'affiliate',
            'action_url' => route('affiliate.dashboard'),
            'action_label' => translate('View affiliate dashboard'),
            'meta' => ['commission_id' => $commission->id],
        ]);
    }

    public function payoutRequested(AffiliatePayout $payout): void
    {
        $payout->loadMissing('user');

        if (! $payout->user) {
            return;
        }

        $this->notifications->notifyAdmins([
            'title' => translate('Payout request'),
            'message' => translate('💰 You have a new payout request: :amount', [
                'amount' => format_currency((float) $payout->amount),
            ]),
            'level' => 'info',
            'category' => 'affiliate',
            'action_url' => route('admin.affiliate.index'),
            'action_label' => translate('Review payout'),
            'meta' => [
                'payout_id' => $payout->id,
                'user_ulid' => $payout->user->ulid,
                'method' => $payout->method,
            ],
        ], 'super-admin');
    }

    public function payoutApproved(AffiliatePayout $payout): void
    {
        $payout->loadMissing('user');

        if (! $payout->user) {
            return;
        }

        $this->notifications->send($payout->user, [
            'title' => translate('Payout approved'),
            'message' => translate('Your payout request has been approved'),
            'level' => 'success',
            'category' => 'affiliate',
            'action_url' => route('affiliate.dashboard'),
            'action_label' => translate('View affiliate dashboard'),
            'meta' => ['payout_id' => $payout->id],
        ]);
    }

    public function payoutCancelled(AffiliatePayout $payout): void
    {
        $payout->loadMissing('user');

        if (! $payout->user) {
            return;
        }

        $this->notifications->send($payout->user, [
            'title' => translate('Payout request cancelled'),
            'message' => translate('❌ Your payout request has been cancelled'),
            'level' => 'warning',
            'category' => 'affiliate',
            'action_url' => route('affiliate.dashboard'),
            'action_label' => translate('View affiliate dashboard'),
            'meta' => ['payout_id' => $payout->id],
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
            'action_url' => route('documents.edit', $document),
            'action_label' => translate('Open document'),
            'meta' => ['document_id' => $document->id],
        ]);
    }

    public function mediaReady(User $user, string $type, ?string $url = null): void
    {
        $this->notifications->send($user, [
            'title' => translate('Media ready'),
            'message' => translate('Your :type is ready to view.', ['type' => $type]),
            'level' => 'success',
            'category' => 'media',
            'action_url' => $url,
            'action_label' => $url ? translate('View') : null,
            'meta' => ['type' => $type],
        ]);
    }

    public function adminAnnouncement(string $title, string $message, string $audience = 'all', ?string $url = null): int
    {
        $sent = 0;
        $queue = settings('notifications_driver', 'reverb') !== 'polling';

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
                        'action_url' => $url,
                        'action_label' => $url ? translate('View') : null,
                    ], $queue);
                    $sent++;
                }
            });

        return $sent;
    }

    public function newToolLaunched(AiTemplate $tool): int
    {
        if (! $tool->is_active) {
            return 0;
        }

        $sent = 0;
        $queue = settings('notifications_driver', 'reverb') !== 'polling';

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
            'action_url' => route('admin.users.show', $user),
            'action_label' => translate('View user'),
            'meta' => ['ip' => $ip, 'user_ulid' => $user->ulid],
        ]);
    }

    public function newUserRegistered(User $user): void
    {
        $this->notifications->notifyAdmins([
            'title' => translate('New user registered'),
            'message' => translate(':name (:email) created an account.', [
                'name' => $user->name,
                'email' => $user->email,
            ]),
            'level' => 'info',
            'category' => 'users',
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
            'action_url' => route('admin.comments.index'),
            'action_label' => translate('Moderate comments'),
            'meta' => ['comment_id' => $comment->id],
        ]);
    }

    public function licenseGracePeriod(int $days): void
    {
        $this->notifications->notifyAdmins([
            'title' => translate('License re-verification warning'),
            'message' => translate('License re-verification failed. :days days grace period remaining.', ['days' => $days]),
            'level' => 'warning',
            'category' => 'license',
        ], 'super-admin');
    }

    public function updateAvailable(string $version): void
    {
        $this->notifications->notifyAdmins([
            'title' => translate('Update available'),
            'message' => translate(':app v:version is available. Update now.', [
                'app' => settings('app_name', 'Application'),
                'version' => $version,
            ]),
            'level' => 'info',
            'category' => 'system',
        ], 'super-admin');
    }

    public function serverHealthWarning(string $checkName): void
    {
        $this->notifications->notifyAdmins([
            'title' => translate('Server health warning'),
            'message' => translate('Health check failed: :check', ['check' => $checkName]),
            'level' => 'error',
            'category' => 'system',
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
        Subscription::query()
            ->with(['user', 'plan'])
            ->where('status', 'active')
            ->whereBetween('current_period_end', [now()->addDays(3)->startOfDay(), now()->addDays(3)->endOfDay()])
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    $this->subscriptionRenewingSoon($subscription);
                    $count++;
                }
            });

        return $count;
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
