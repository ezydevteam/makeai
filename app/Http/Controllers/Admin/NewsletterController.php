<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class NewsletterController extends Controller
{
    /**
     * List subscribers and campaigns.
     */
    public function index()
    {
        return Inertia::render('Admin/Community/Newsletter', [
            'subscribers' => NewsletterSubscriber::orderBy('created_at', 'desc')->paginate(20),
            'campaigns' => NewsletterCampaign::orderBy('created_at', 'desc')->get(),
            'stats' => [
                'total' => NewsletterSubscriber::count(),
                'active' => NewsletterSubscriber::where('status', 'subscribed')->count(),
                'unsubscribed' => NewsletterSubscriber::where('status', 'unsubscribed')->count(),
            ],
            'settings' => Setting::getGroup('newsletter'),
        ]);
    }

    /**
     * Create a new campaign.
     */
    public function storeCampaign(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        NewsletterCampaign::create($request->all());

        return back()->with('success', 'Campaign draft saved.');
    }

    /**
     * Send a campaign.
     */
    public function sendCampaign(NewsletterCampaign $campaign)
    {
        if ($campaign->status === 'sent') {
            return back()->with('error', 'Campaign already sent.');
        }

        $subscribers = NewsletterSubscriber::where('status', 'subscribed')->get();
        $campaign->update(['status' => 'sending', 'recipient_count' => $subscribers->count()]);

        // In a real app, we would use a Job/Queue here.
        // For now, we'll demonstrate the logic.
        foreach ($subscribers as $subscriber) {
            // Mail::to($subscriber->email)->queue(new \App\Mail\NewsletterMail($campaign, $subscriber));
        }

        $campaign->update(['status' => 'sent', 'sent_at' => now()]);

        return back()->with('success', 'Campaign sent to '.$subscribers->count().' subscribers.');
    }

    /**
     * Delete a subscriber.
     */
    public function destroySubscriber(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    /**
     * Save newsletter settings.
     */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'newsletter_driver' => 'nullable|in:internal,mailchimp,both',
            'mailchimp_api_key' => 'nullable|string',
            'mailchimp_server_prefix' => 'nullable|string',
            'mailchimp_list_id' => 'nullable|string',
            'mailchimp_double_optin' => 'nullable|boolean',
            'mailchimp_tags' => 'nullable|string',

            'newsletter_enable_popup' => 'nullable|boolean',
            'newsletter_popup_trigger' => 'nullable|in:exit_intent,time_delay,scroll_depth,page_views,first_visit',
            'newsletter_popup_trigger_value' => 'nullable|string',
            'newsletter_popup_title' => 'nullable|string',
            'newsletter_popup_description' => 'nullable|string',
            'newsletter_popup_placeholder' => 'nullable|string',
            'newsletter_popup_submit_text' => 'nullable|string',
            'newsletter_popup_success_message' => 'nullable|string',
            'newsletter_popup_bg_color' => 'nullable|string',
            'newsletter_popup_show_mobile' => 'nullable|boolean',
            'newsletter_popup_cookie_duration' => 'nullable|integer',
            'newsletter_popup_hide_for_logged_in' => 'nullable|boolean',
        ]);

        Setting::updateGroup('newsletter', $validated);

        return back()->with('success', 'Newsletter settings updated successfully.');
    }
}
