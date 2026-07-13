<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsletterCampaignRequest;
use App\Http\Requests\Admin\NewsletterSettingsRequest;
use App\Jobs\SendNewsletterCampaign;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NewsletterController extends Controller
{
    private const SECRET_KEYS = [
        'mailchimp_api_key',
    ];

    /**
     * List subscribers.
     */
    public function index(Request $request)
    {
        $subscribersQuery = NewsletterSubscriber::query()->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $subscribersQuery->where(function ($query) use ($search) {
                $query->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $subscribersQuery->where('status', $request->string('status')->toString());
        }

        $stats = $this->getStats();

        return Inertia::render('Admin/Marketing/Newsletter/Subscribers', [
            'subscribers' => $subscribersQuery->paginate(20)->withQueryString(),
            'stats' => $stats,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
        ]);
    }

    /**
     * List campaigns.
     */
    public function campaigns()
    {
        $stats = $this->getCampaignStats();

        return Inertia::render('Admin/Marketing/Newsletter/Campaigns', [
            'campaigns' => NewsletterCampaign::orderBy('created_at', 'desc')->paginate(10),
            'stats' => $stats,
            'isMailConfigured' => filled(settings('mail_driver')),
        ]);
    }

    /**
     * Edit settings.
     */
    public function settings()
    {
        $settings = Setting::getGroup('newsletter');
        $configuredSecrets = [];

        foreach (self::SECRET_KEYS as $key) {
            $configuredSecrets[$key] = filled($settings[$key] ?? null);
            unset($settings[$key]);
        }

        return Inertia::render('Admin/Marketing/Newsletter/Settings', [
            'settings' => $settings,
            'configuredSecrets' => $configuredSecrets,
        ]);
    }

    /**
     * Get campaign stats.
     */
    private function getCampaignStats(): array
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Campaigns
        $totalCurrent = NewsletterCampaign::count();
        $totalPrevious = NewsletterCampaign::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Queued
        $queuedCurrent = NewsletterCampaign::where('status', 'sending')->count();
        $queuedPrevious = NewsletterCampaign::where('status', 'sending')->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Sent — driven by sent_at (when the send ran), not when the row was created.
        $sentCurrent = NewsletterCampaign::where('status', 'sent')->count();
        $sentPrevious = NewsletterCampaign::whereNotNull('sent_at')->where('sent_at', '<', $sevenDaysAgo)->count();

        // 4. Failed
        $failedCurrent = NewsletterCampaign::where('failed_count', '>', 0)->count();
        $failedPrevious = NewsletterCampaign::where('failed_count', '>', 0)->whereNotNull('sent_at')->where('sent_at', '<', $sevenDaysAgo)->count();

        return [
            'total' => [
                'value' => $totalCurrent,
                'comparison' => $this->calculateComparison($totalCurrent, $totalPrevious),
            ],
            'queued' => [
                'value' => $queuedCurrent,
                'comparison' => $this->calculateComparison($queuedCurrent, $queuedPrevious),
            ],
            'sent' => [
                'value' => $sentCurrent,
                'comparison' => $this->calculateComparison($sentCurrent, $sentPrevious),
            ],
            'failed' => [
                'value' => $failedCurrent,
                'comparison' => $this->calculateComparison($failedCurrent, $failedPrevious),
            ],
            // Include audience counts for recipientCount calculation on creation form
            'active' => NewsletterSubscriber::where('status', 'subscribed')->count(),
            'users_all' => User::where('email_marketing', true)->count(),
            'users_active' => User::where('email_marketing', true)->where('last_login_at', '>=', now()->subDays(30))->count(),
            'users_inactive' => User::where('email_marketing', true)->where(function ($query) {
                $query->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(30));
            })->count(),
            'users_pro' => isProAvailable()
                ? User::where('email_marketing', true)->whereIn('subscription_status', ['active', 'trialing'])->count()
                : 0,
            'users_free' => User::where('email_marketing', true)->where(function ($query) {
                $query->whereNull('subscription_status')->orWhereNotIn('subscription_status', ['active', 'trialing']);
            })->count(),
        ];
    }

    /**
     * Get newsletter stats.
     */
    private function getStats(): array
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Subscribers
        $subscribersCurrent = NewsletterSubscriber::count();
        $subscribersPrevious = NewsletterSubscriber::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Active Subscribers — subscribed before T and not unsubscribed by T
        //    (reconstructed from subscribed_at / unsubscribed_at, not created_at).
        $activeCurrent = NewsletterSubscriber::where('status', 'subscribed')->count();
        $activePrevious = NewsletterSubscriber::where('subscribed_at', '<', $sevenDaysAgo)
            ->where(fn ($q) => $q->whereNull('unsubscribed_at')->orWhere('unsubscribed_at', '>=', $sevenDaysAgo))
            ->count();

        // 3. Unsubscribed — those already unsubscribed a week ago (unsubscribed_at drives it).
        $unsubscribedCurrent = NewsletterSubscriber::where('status', 'unsubscribed')->count();
        $unsubscribedPrevious = NewsletterSubscriber::whereNotNull('unsubscribed_at')->where('unsubscribed_at', '<', $sevenDaysAgo)->count();

        // 4. Sent Campaigns — driven by sent_at, not created_at.
        $campaignsCurrent = NewsletterCampaign::where('status', 'sent')->count();
        $campaignsPrevious = NewsletterCampaign::whereNotNull('sent_at')->where('sent_at', '<', $sevenDaysAgo)->count();

        return [
            'total' => [
                'value' => $subscribersCurrent,
                'comparison' => $this->calculateComparison($subscribersCurrent, $subscribersPrevious),
            ],
            'active' => [
                'value' => $activeCurrent,
                'comparison' => $this->calculateComparison($activeCurrent, $activePrevious),
            ],
            'unsubscribed' => [
                'value' => $unsubscribedCurrent,
                'comparison' => $this->calculateComparison($unsubscribedCurrent, $unsubscribedPrevious),
            ],
            'campaigns' => [
                'value' => $campaignsCurrent,
                'comparison' => $this->calculateComparison($campaignsCurrent, $campaignsPrevious),
            ],
            'users_all' => User::where('email_marketing', true)->count(),
            'users_active' => User::where('email_marketing', true)->where('last_login_at', '>=', now()->subDays(30))->count(),
            'users_inactive' => User::where('email_marketing', true)->where(function ($query) {
                $query->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(30));
            })->count(),
            'users_pro' => isProAvailable()
                ? User::where('email_marketing', true)->whereIn('subscription_status', ['active', 'trialing'])->count()
                : 0,
            'users_free' => User::where('email_marketing', true)->where(function ($query) {
                $query->whereNull('subscription_status')->orWhereNotIn('subscription_status', ['active', 'trialing']);
            })->count(),
        ];
    }

    /**
     * Create a new campaign.
     */
    public function storeCampaign(NewsletterCampaignRequest $request)
    {
        $this->authorizeNewsletter();
        $data = $request->validated();

        if ($data['audience'] === 'users_pro' && ! isProAvailable()) {
            return back()->with('error', translate('Pro audience is only available when subscriptions are enabled.'));
        }

        NewsletterCampaign::create($data);

        return back()->with('success', translate('Campaign draft saved.'));
    }

    /**
     * Send a campaign.
     */
    public function sendCampaign(NewsletterCampaign $campaign)
    {
        $this->authorizeNewsletter();
        if (blank(settings('mail_driver'))) {
            return back()->with('error', translate('Mail configuration is not set. Please configure mail settings first.'));
        }

        if (in_array($campaign->status, ['sending', 'sent'], true)) {
            return back()->with('error', translate('Campaign is already queued or sent.'));
        }

        if ($campaign->audience === 'users_pro' && ! isProAvailable()) {
            return back()->with('error', translate('Pro audience is only available when subscriptions are enabled.'));
        }

        $recipientCount = SendNewsletterCampaign::recipientCountFor($campaign->audience);

        if ($recipientCount === 0) {
            return back()->with('warning', translate('No active subscribers found.'));
        }

        // Do NOT set status to 'sending' here. The job owns that transition via an
        // optimistic lock (whereNotIn(['sending','sent']) → 'sending'); pre-setting
        // it makes the lock fail so the job bails and the campaign silently never
        // sends. That same lock also makes a double-dispatch safe — only the first
        // job wins the claim and actually sends.
        $campaign->update(['recipient_count' => $recipientCount]);

        SendNewsletterCampaign::dispatch($campaign->id)->onQueue('emails');

        return back()->with('success', translate('Campaign sending has been queued for :count subscribers.', ['count' => $recipientCount]));
    }

    /**
     * Delete a campaign.
     */
    public function destroyCampaign(NewsletterCampaign $campaign)
    {
        $this->authorizeNewsletter();
        if ($campaign->status === 'sending') {
            return back()->with('error', translate('Cannot delete a campaign that is currently sending.'));
        }

        $campaign->delete();

        return back()->with('success', translate('Campaign deleted.'));
    }

    /**
     * Update a draft campaign.
     */
    public function updateCampaign(NewsletterCampaignRequest $request, NewsletterCampaign $campaign)
    {
        $this->authorizeNewsletter();
        if ($campaign->status !== 'draft') {
            return back()->with('error', translate('Only draft campaigns can be edited.'));
        }

        $data = $request->validated();

        if ($data['audience'] === 'users_pro' && ! isProAvailable()) {
            return back()->with('error', translate('Pro audience is only available when subscriptions are enabled.'));
        }

        $campaign->update($data);

        return back()->with('success', translate('Campaign updated.'));
    }

    /**
     * Send a test email for a campaign.
     */
    public function testCampaign(NewsletterCampaign $campaign)
    {
        $this->authorizeNewsletter();
        if (blank(settings('mail_driver'))) {
            return back()->with('error', translate('Mail configuration is not set. Please configure mail settings first.'));
        }

        $adminEmail = auth('admin')->user()?->email;

        if (! $adminEmail) {
            return back()->with('error', translate('No admin email found.'));
        }

        dispatch(function () use ($campaign, $adminEmail) {
            $rendered = SendNewsletterCampaign::renderCampaign($campaign, [
                'email' => $adminEmail,
                'name' => 'Test Admin',
                'unsubscribe_url' => '#',
            ]);

            \Illuminate\Support\Facades\Mail::html($rendered['html'], function ($message) use ($adminEmail, $rendered) {
                $message->to($adminEmail)->subject('[TEST] '.$rendered['subject']);
            });
        })->onQueue('emails');

        return back()->with('success', translate('Test email queued to :email.', ['email' => $adminEmail]));
    }

    /**
     * Delete a subscriber.
     */
    public function destroySubscriber(NewsletterSubscriber $subscriber)
    {
        $this->authorizeNewsletter();
        $subscriber->delete();

        return back()->with('success', translate('Subscriber removed.'));
    }

    /**
     * Save newsletter settings.
     */
    public function saveSettings(NewsletterSettingsRequest $request)
    {
        $this->authorizeNewsletter();
        foreach ($request->validated() as $key => $value) {
            if (in_array($key, self::SECRET_KEYS, true) && blank($value)) {
                continue;
            }

            $type = in_array($key, self::SECRET_KEYS, true) ? 'encrypted' : (is_bool($value) ? 'boolean' : 'string');
            settings_set($key, $value, $type, 'newsletter');
        }

        return back()->with('success', translate('Newsletter settings updated successfully.'));
    }

    public function retryFailed(NewsletterCampaign $campaign)
    {
        $this->authorizeNewsletter();
        if ($campaign->status !== 'sent') {
            return back()->with('error', translate('Only sent campaigns with failures can be retried.'));
        }

        $failedCount = NewsletterCampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->count();

        if ($failedCount === 0) {
            return back()->with('warning', translate('No failed recipients to retry.'));
        }

        SendNewsletterCampaign::dispatch($campaign->id, true)->onQueue('emails');

        return back()->with('success', translate('Retrying :count failed recipients.', ['count' => $failedCount]));
    }

    /**
     * Return the failed recipients (with their delivery error) for a campaign so the
     * admin can see *why* sends failed, not just the failed count. Capped to keep the
     * payload bounded; the total is returned so the UI can note truncation.
     */
    public function failures(NewsletterCampaign $campaign)
    {
        $this->authorizeNewsletter();

        $recipients = NewsletterCampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->latest('updated_at')
            ->limit(200)
            ->get(['email', 'name', 'error_message', 'updated_at']);

        return response()->json([
            'failed_count' => (int) $campaign->failed_count,
            'recipients' => $recipients->map(fn (NewsletterCampaignRecipient $r) => [
                'email' => $r->email,
                'name' => $r->name,
                'error' => $r->error_message,
                'failed_at' => $r->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    private function authorizeNewsletter(): void
    {
        if (! auth('admin')->check()) {
            abort(403, translate('Unauthorized.'));
        }
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
