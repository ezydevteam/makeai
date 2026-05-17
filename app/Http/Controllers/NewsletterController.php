<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class NewsletterController extends Controller
{
    /**
     * Subscribe to newsletter (Frontend).
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $driver = Setting::getValue('newsletter_driver', 'internal');
        $saveLocally = in_array($driver, ['internal', 'both']);
        $syncMailchimp = in_array($driver, ['mailchimp', 'both']);

        $message = 'You have been subscribed to our newsletter!';
        $subscriber = null;

        if ($saveLocally) {
            $subscriber = NewsletterSubscriber::firstOrCreate(
                ['email' => $request->email],
                [
                    'name' => $request->name,
                    'status' => 'subscribed',
                    'token' => NewsletterSubscriber::generateToken(),
                ]
            );

            if (! $subscriber->wasRecentlyCreated && $subscriber->status !== 'subscribed') {
                $subscriber->update(['status' => 'subscribed', 'subscribed_at' => now()]);
                $message = 'Welcome back! Your subscription is active again.';
            } elseif (! $subscriber->wasRecentlyCreated) {
                $message = 'You are already subscribed.';
            }
        }

        if ($syncMailchimp) {
            $this->syncToMailchimp($request->email, $request->name, 'subscribed');
        }

        return back()->with('success', $message);
    }

    /**
     * Unsubscribe from newsletter.
     */
    public function unsubscribe(string $token)
    {
        $subscriber = NewsletterSubscriber::where('token', $token)->firstOrFail();

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        $driver = Setting::getValue('newsletter_driver', 'internal');
        if (in_array($driver, ['mailchimp', 'both'])) {
            $this->syncToMailchimp($subscriber->email, $subscriber->name, 'unsubscribed');
        }

        return Inertia::render('Newsletter/Unsubscribed', [
            'email' => $subscriber->email,
        ]);
    }

    /**
     * Sync subscriber status to Mailchimp.
     */
    private function syncToMailchimp(string $email, ?string $name, string $status)
    {
        $apiKey = Setting::getValue('mailchimp_api_key');
        $serverPrefix = Setting::getValue('mailchimp_server_prefix');
        $listId = Setting::getValue('mailchimp_list_id');

        if (! $apiKey || ! $serverPrefix || ! $listId) {
            return;
        }

        $doubleOptin = Setting::getValue('mailchimp_double_optin', false);
        $tagsString = Setting::getValue('mailchimp_tags', '');
        $tags = array_filter(array_map('trim', explode(',', $tagsString)));

        // Mailchimp uses md5 hash of lowercase email for the member ID
        $subscriberHash = md5(strtolower($email));
        $url = "https://{$serverPrefix}.api.mailchimp.com/3.0/lists/{$listId}/members/{$subscriberHash}";

        // Map status to Mailchimp status
        $mcStatus = $status === 'subscribed' ? ($doubleOptin ? 'pending' : 'subscribed') : 'unsubscribed';

        $data = [
            'email_address' => $email,
            'status_if_new' => $mcStatus,
            'status' => $mcStatus,
        ];

        if ($name) {
            $data['merge_fields'] = [
                'FNAME' => $name,
            ];
        }

        if (! empty($tags)) {
            $data['tags'] = $tags;
        }

        try {
            Http::withBasicAuth('anystring', $apiKey)
                ->put($url, $data);
        } catch (\Exception $e) {
            Log::error('Mailchimp sync failed: '.$e->getMessage());
        }
    }
}
