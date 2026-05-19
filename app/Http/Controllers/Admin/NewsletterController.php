<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsletterCampaignRequest;
use App\Http\Requests\Admin\NewsletterSettingsRequest;
use App\Jobs\SendNewsletterCampaign;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Models\User;
use Inertia\Inertia;

class NewsletterController extends Controller
{
    private const SECRET_KEYS = [
        'mailchimp_api_key',
    ];

    /**
     * List subscribers and campaigns.
     */
    public function index()
    {
        $settings = Setting::getGroup('newsletter');
        $configuredSecrets = [];

        foreach (self::SECRET_KEYS as $key) {
            $configuredSecrets[$key] = filled($settings[$key] ?? null);
            unset($settings[$key]);
        }

        return Inertia::render('Admin/Community/Newsletter', [
            'subscribers' => NewsletterSubscriber::orderBy('created_at', 'desc')->paginate(20),
            'campaigns' => NewsletterCampaign::orderBy('created_at', 'desc')->paginate(10),
            'stats' => [
                'total' => NewsletterSubscriber::count(),
                'active' => NewsletterSubscriber::where('status', 'subscribed')->count(),
                'unsubscribed' => NewsletterSubscriber::where('status', 'unsubscribed')->count(),
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
            ],
            'settings' => $settings,
            'configuredSecrets' => $configuredSecrets,
        ]);
    }

    /**
     * Create a new campaign.
     */
    public function storeCampaign(NewsletterCampaignRequest $request)
    {
        $data = $request->validated();
        $data['content'] = $this->sanitizeHtml($data['content']);

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

        $campaign->update([
            'status' => 'sending',
            'recipient_count' => $recipientCount,
            'sent_count' => 0,
            'failed_count' => 0,
        ]);

        SendNewsletterCampaign::dispatch($campaign->id)->onQueue('emails');

        return back()->with('success', translate('Campaign sending has been queued for :count subscribers.', ['count' => $recipientCount]));
    }

    /**
     * Delete a subscriber.
     */
    public function destroySubscriber(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();

        return back()->with('success', translate('Subscriber removed.'));
    }

    /**
     * Save newsletter settings.
     */
    public function saveSettings(NewsletterSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            if (in_array($key, self::SECRET_KEYS, true) && blank($value)) {
                continue;
            }

            $type = in_array($key, self::SECRET_KEYS, true) ? 'encrypted' : (is_bool($value) ? 'boolean' : 'string');
            settings_set($key, $value, $type, 'newsletter');
        }

        return back()->with('success', translate('Newsletter settings updated successfully.'));
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? '';
        $html = preg_replace('/\son\w+=("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/javascript:/i', '', $html) ?? '';

        return strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h1><h2><h3><h4><h5><a><img><table><thead><tbody><tr><th><td><pre><code><hr>');
    }
}
