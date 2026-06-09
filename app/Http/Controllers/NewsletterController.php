<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterSubscribeRequest;
use App\Jobs\SyncMailchimpSubscriber;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\MailService;
use Illuminate\Support\Facades\Response;
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
        $useDoubleOptin = (bool) settings('newsletter_double_optin', false);

        $message = translate('You have been subscribed to our newsletter!');
        $subscriber = null;

        if ($saveLocally) {
            $confirmToken = $useDoubleOptin ? NewsletterSubscriber::generateToken() : null;

            $subscriber = NewsletterSubscriber::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'] ?? null,
                    'status' => $useDoubleOptin ? 'pending' : 'subscribed',
                    'token' => NewsletterSubscriber::generateToken(),
                    'confirm_token' => $confirmToken,
                ]
            );

            if ($subscriber->wasRecentlyCreated && $useDoubleOptin) {
                $this->sendConfirmationEmail($subscriber);
                $message = translate('Please check your email to confirm your subscription.');
            } elseif (! $subscriber->wasRecentlyCreated && $subscriber->status !== 'subscribed') {
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
     * Confirm newsletter subscription (double opt-in).
     */
    public function confirm(string $token)
    {
        $subscriber = NewsletterSubscriber::where('confirm_token', $token)->where('status', 'pending')->first();

        if (! $subscriber) {
            return Inertia::render('Newsletter/Unsubscribed', [
                'email' => null,
                'message' => translate('This confirmation link is invalid or has expired.'),
            ]);
        }

        $subscriber->update([
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'confirm_token' => null,
        ]);

        return Inertia::render('Newsletter/Unsubscribed', [
            'email' => $subscriber->email,
            'message' => translate('Your subscription has been confirmed!'),
        ]);
    }

    /**
     * Track email open (beacon pixel).
     */
    public function trackOpen(NewsletterCampaign $campaign, string $email)
    {
        $decodedEmail = base64_decode($email, true);

        if ($decodedEmail) {
            $campaign->increment('opened_count');

            \App\Models\NewsletterCampaignRecipient::where('campaign_id', $campaign->id)
                ->where('email', $decodedEmail)
                ->where('status', 'sent')
                ->update(['opened_at' => now()]);
        }

        $pixel = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==', true);

        return response($pixel ?: '', 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen((string) $pixel),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
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

    private function sendConfirmationEmail(NewsletterSubscriber $subscriber): void
    {
        $confirmUrl = route('newsletter.confirm', $subscriber->confirm_token);

        app(MailService::class)->send('newsletter_confirm', $subscriber->email, [
            'user_name' => $subscriber->name ?: $subscriber->email,
            'user_email' => $subscriber->email,
            'confirm_url' => $confirmUrl,
        ]);
    }
}
