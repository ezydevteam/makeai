<?php

namespace App\Services;

use App\Jobs\SendTemplatedEmail;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Auth-related template slugs that should always be sent (never opted out).
     */
    private const AUTH_SLUGS = [
        'email_verify_otp',
        'reset_password_otp',
        'login_otp',
        'password_changed',
        // Security alert sent to the previous address on an email change — must
        // never be suppressible by notification preferences.
        'email_changed',
    ];

    /**
     * Map template slugs to notification preference groups.
     */
    private const SLUG_TO_GROUP_MAP = [
        'credits_low' => 'billing',
        'admin_notification' => 'admin',
        'newsletter_confirm' => 'updates',
        'newsletter_campaign' => 'updates',
    ];

    /**
     * Send a templated email (Dispatches to queue by default)
     */
    public function send(string $slug, string $to, array $data = [], bool $queue = true): void
    {
        if ($queue) {
            SendTemplatedEmail::dispatch($slug, $to, $data)->onQueue('emails');

            return;
        }

        $this->processSend($slug, $to, $data);
    }

    /**
     * Internal send logic (Used by job or direct send)
     */
    public function processSend(string $slug, string $to, array $data = []): void
    {
        $template = MailTemplate::where('slug', $slug)->where('is_active', true)->first();

        if (! $template) {
            // Surface the gap instead of silently dropping the email — a dispatched
            // slug with no active template means a broken/disabled notification.
            \Illuminate\Support\Facades\Log::warning("MailService: no active mail template for slug '{$slug}' — email not sent.", [
                'recipient' => $to,
            ]);

            return;
        }

        if ($template->requires_pro && ! isProAvailable()) {
            return;
        }

        // Check email preferences (skip for auth emails)
        if (! $this->shouldSendEmail($slug, $to)) {
            return;
        }

        $rendered = $template->render($data);
        $htmlContent = MailTemplate::wrapInLayout($rendered['content'], $data);

        try {
            Mail::html($htmlContent, function ($message) use ($to, $rendered) {
                $message->to($to)->subject($rendered['subject']);
            });

            MailLog::create([
                'template_slug' => $slug,
                'recipient_email' => $to,
                'subject' => $rendered['subject'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            MailLog::create([
                'template_slug' => $slug,
                'recipient_email' => $to,
                'subject' => $rendered['subject'],
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if an email should be sent based on user preferences.
     * Auth emails always pass through.
     */
    public function shouldSendEmail(string $slug, string $to): bool
    {
        // Auth emails are always sent
        if (in_array($slug, self::AUTH_SLUGS, true)) {
            return true;
        }

        // Find user by email to check preferences
        $user = User::where('email', $to)->first();

        if (! $user) {
            // No user found — allow sending (could be a non-user recipient)
            return true;
        }

        // Map slug to group and check preference
        $group = $this->getGroupForSlug($slug);

        if (! $group) {
            // Unknown slug — allow sending (e.g., subscription templates, custom templates)
            return true;
        }

        return $user->wantsEmailNotification($group);
    }

    /**
     * Get the notification preference group for a template slug.
     */
    public function getGroupForSlug(string $slug): ?string
    {
        // Check explicit mapping first
        if (isset(self::SLUG_TO_GROUP_MAP[$slug])) {
            return self::SLUG_TO_GROUP_MAP[$slug];
        }

        // Check template category as fallback
        $template = MailTemplate::where('slug', $slug)->first();

        if ($template) {
            return match ($template->category) {
                'auth' => null, // Auth emails always sent
                'subscription' => 'billing',
                'newsletter' => 'updates',
                'account' => 'admin',
                'affiliate' => 'affiliate',
                'content' => 'content',
                // Support and export mail is transactional: a reply on a ticket the
                // user opened, or a file they asked for. Never preference-gated.
                'support', 'export' => null,
                default => null,
            };
        }

        return null;
    }

    public function preview(string $slug, array $data = []): string
    {
        $template = MailTemplate::where('slug', $slug)->first();
        if (! $template) {
            return translate('Template not found');
        }

        if ($template->requires_pro && ! isProAvailable()) {
            return translate('Template not available for this license.');
        }

        $rendered = $template->render($data);

        return MailTemplate::wrapInLayout($rendered['content'], $data);
    }

    public function getVariables(): array
    {
        return [
            'site_name',
            'site_url',
            'site_logo_url',
            'support_email',
            'current_year',
            'unsubscribe_url',
            'user_name',
            'user_email',
            'otp_code',
            'plan_name',
            'credits',
            'amount',
            'ticket_number',
            'ticket_subject',
            'ticket_priority',
            'ticket_department',
            'ticket_url',
            'customer_name',
            'export_name',
            'download_url',
            'expires_at',
            'post_title',
            'comment_url',
            'tool_name',
            'review_url',
        ];
    }
}
