<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterSubscribeRequest;
use App\Jobs\SyncMailchimpSubscriber;
use App\Models\NewsletterSubscriber;
use Inertia\Inertia;

class NewsletterController extends Controller
{
    /**
     * Subscribe to newsletter (Frontend).
     */
    public function subscribe(NewsletterSubscribeRequest $request)
    {
        $data = $request->validated();

        $driver = settings('newsletter_driver', 'internal');
        $saveLocally = in_array($driver, ['internal', 'both']);
        $syncMailchimp = in_array($driver, ['mailchimp', 'both']);

        $message = translate('You have been subscribed to our newsletter!');
        $subscriber = null;

        if ($saveLocally) {
            $subscriber = NewsletterSubscriber::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'] ?? null,
                    'status' => 'subscribed',
                    'token' => NewsletterSubscriber::generateToken(),
                ]
            );

            if (! $subscriber->wasRecentlyCreated && $subscriber->status !== 'subscribed') {
                $subscriber->update(['status' => 'subscribed', 'subscribed_at' => now()]);
                $message = translate('Welcome back! Your subscription is active again.');
            } elseif (! $subscriber->wasRecentlyCreated) {
                $message = translate('You are already subscribed.');
            }
        }

        if ($syncMailchimp) {
            SyncMailchimpSubscriber::dispatch($data['email'], $data['name'] ?? null, 'subscribed')->onQueue('webhooks');
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

        $driver = settings('newsletter_driver', 'internal');
        if (in_array($driver, ['mailchimp', 'both'])) {
            SyncMailchimpSubscriber::dispatch($subscriber->email, $subscriber->name, 'unsubscribed')->onQueue('webhooks');
        }

        return Inertia::render('Newsletter/Unsubscribed', [
            'email' => $subscriber->email,
        ]);
    }
}
