<?php

namespace App\Jobs;

use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewsletterCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        protected int $campaignId,
        protected bool $retryOnly = false
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $campaign = NewsletterCampaign::find($this->campaignId);

        // A retry runs specifically on an already-'sent' campaign (to re-send its
        // failed recipients), so only bail on 'sent' for a normal (non-retry) send.
        if (! $campaign || ($campaign->status === 'sent' && ! $this->retryOnly)) {
            return;
        }

        // Optimistic lock: only one job per campaign can transition to 'sending'
        $updated = NewsletterCampaign::where('id', $this->campaignId)
            ->whereNotIn('status', ['sending', 'sent'])
            ->update(['status' => 'sending', 'started_at' => now()]);

        if (! $updated && $this->retryOnly === false) {
            return;
        }

        if ($this->retryOnly) {
            $this->handleRetry($campaign);
            return;
        }

        $recipientCount = self::recipientCountFor($campaign->audience);

        $campaign->update([
            'status' => 'sending',
            'recipient_count' => $recipientCount,
            'sent_count' => 0,
            'failed_count' => 0,
            'finished_at' => null,
        ]);

        if ($campaign->audience === 'subscribers') {
            NewsletterSubscriber::query()
                ->where('status', 'subscribed')
                ->orderBy('id')
                ->chunkById(100, function ($subscribers) use ($campaign) {
                    foreach ($subscribers as $subscriber) {
                        $this->sendToRecipient($campaign, [
                            'email' => $subscriber->email,
                            'name' => $subscriber->name,
                            'subscriber_id' => $subscriber->id,
                            'user_id' => null,
                            'unsubscribe_url' => route('newsletter.unsubscribe', $subscriber->token),
                        ]);
                    }
                });
        } else {
            self::userAudienceQuery($campaign->audience)
                ->orderBy('id')
                ->chunkById(100, function ($users) use ($campaign) {
                    foreach ($users as $user) {
                        $subscriber = NewsletterSubscriber::firstOrCreate(
                            ['email' => $user->email],
                            [
                                'name' => $user->name,
                                'status' => 'subscribed',
                                'token' => NewsletterSubscriber::generateToken(),
                            ]
                        );

                        $this->sendToRecipient($campaign, [
                            'email' => $user->email,
                            'name' => $user->name,
                            'subscriber_id' => $subscriber->id,
                            'user_id' => $user->id,
                            'unsubscribe_url' => route('newsletter.unsubscribe', $subscriber->token),
                        ]);
                    }
                });
        }

        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public static function recipientCountFor(?string $audience): int
    {
        if ($audience === 'subscribers' || $audience === null) {
            return NewsletterSubscriber::where('status', 'subscribed')->count();
        }

        return self::userAudienceQuery($audience)->count();
    }

    private function handleRetry(NewsletterCampaign $campaign): void
    {
        // Claim each failed recipient atomically before sending: flip 'failed' →
        // 'sending' only if it is still 'failed'. A concurrent retry job that has
        // already claimed a recipient gets 0 affected rows and is skipped, so no
        // recipient is ever emailed twice by overlapping retries. (IDs are collected
        // up front so we don't chunk over the same status column we're mutating.)
        NewsletterCampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($id) use ($campaign) {
                $claimed = NewsletterCampaignRecipient::whereKey($id)
                    ->where('status', 'failed')
                    ->update(['status' => 'sending']);

                if (! $claimed) {
                    return; // another retry job already took this recipient
                }

                $recipient = NewsletterCampaignRecipient::with('subscriber')->find($id);

                if (! $recipient) {
                    return;
                }

                $this->sendToRecipient($campaign, [
                    'email' => $recipient->email,
                    'name' => $recipient->name,
                    'subscriber_id' => $recipient->subscriber_id,
                    'user_id' => $recipient->user_id,
                    'unsubscribe_url' => route('newsletter.unsubscribe', $recipient->subscriber?->token ?? ''),
                ]);
            });
    }

    private static function userAudienceQuery(string $audience)
    {
        $query = User::query()
            ->where('email_marketing', true)
            ->where('is_active', true)
            ->where('is_banned', false);

        return match ($audience) {
            'users_active' => $query->where('last_login_at', '>=', now()->subDays(30)),
            'users_inactive' => $query->where(function ($query) {
                $query->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(30));
            }),
            'users_pro' => $query->whereIn('subscription_status', ['active', 'trialing']),
            'users_free' => $query->where(function ($query) {
                $query->whereNull('subscription_status')->orWhereNotIn('subscription_status', ['active', 'trialing']);
            }),
            default => $query,
        };
    }

    /**
     * @param  array{email:string,name:?string,subscriber_id:?int,user_id:?int,unsubscribe_url:string}  $recipientData
     */
    private function sendToRecipient(NewsletterCampaign $campaign, array $recipientData): void
    {
        $recipient = NewsletterCampaignRecipient::updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'email' => $recipientData['email'],
            ],
            [
                'subscriber_id' => $recipientData['subscriber_id'],
                'user_id' => $recipientData['user_id'],
                'name' => $recipientData['name'],
                'status' => 'pending',
                'error_message' => null,
            ]
        );

        $rendered = self::renderCampaign($campaign, $recipientData);

        try {
            Mail::html($rendered['html'], function ($message) use ($recipientData, $rendered) {
                $message->to($recipientData['email'], $recipientData['name'])->subject($rendered['subject']);
            });

            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $campaign->increment('sent_count');

            MailLog::create([
                'template_slug' => 'newsletter_campaign',
                'recipient_email' => $recipientData['email'],
                'recipient_name' => $recipientData['name'],
                'subject' => $rendered['subject'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $campaign->increment('failed_count');

            MailLog::create([
                'template_slug' => 'newsletter_campaign',
                'recipient_email' => $recipientData['email'],
                'recipient_name' => $recipientData['name'],
                'subject' => $rendered['subject'],
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * @param  array{email:string,name:?string,unsubscribe_url:string}  $recipientData
     */
    public static function renderCampaign(NewsletterCampaign $campaign, array $recipientData): array
    {
        $content = $campaign->content;

        if (! str_contains($content, '{unsubscribe_url}') && ! str_contains($content, $recipientData['unsubscribe_url'])) {
            $content .= '<p style="margin-top:24px;font-size:12px;color:#6b7280;"><a href="{unsubscribe_url}">'.translate('Unsubscribe').'</a></p>';
        }

        $variables = [
            'site_name' => settings('app_name', config('app.name')),
            'site_url' => settings('app_url', config('app.url')),
            'site_logo_url' => settings('site_logo_light', ''),
            // ?: so a seeded-but-empty site_support_email falls back to from-address.
            'support_email' => settings('site_support_email') ?: settings('mail_from_address', ''),
            'current_year' => now()->year,
            'year' => now()->year,
            'user_name' => $recipientData['name'] ?: $recipientData['email'],
            'user_email' => $recipientData['email'],
            'unsubscribe_url' => $recipientData['unsubscribe_url'],
            'campaign_subject' => $campaign->subject,
            'campaign_title' => $campaign->subject,
            'campaign_content' => $campaign->content,
        ];

        $template = MailTemplate::where('slug', 'newsletter_campaign')
            ->where('is_active', true)
            ->first();

        $subjectTemplate = $template?->subject ?: $campaign->subject;
        $bodyTemplate = $template?->content ?: $content;

        $subject = self::replaceVariables($subjectTemplate, $variables);
        $body = self::replaceVariables($bodyTemplate, array_merge($variables, [
            'campaign_content' => $content,
        ]));
        $layout = settings('mail_layout', '{content}');
        $pixelUrl = route('newsletter.open', [
            'campaign' => $campaign->id,
            'email' => base64_encode($recipientData['email']),
        ]);
        $trackingPixel = '<img src="'.$pixelUrl.'" width="1" height="1" alt="" style="display:none" />';

        return [
            'subject' => $subject,
            'html' => str_replace('{content}', $body, $layout).$trackingPixel,
        ];
    }

    private static function replaceVariables(string $value, array $variables): string
    {
        foreach ($variables as $key => $replacement) {
            $value = str_replace('{'.$key.'}', (string) $replacement, $value);
        }

        return $value;
    }
}
