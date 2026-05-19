<?php

namespace App\Services;

use App\Jobs\SendTemplatedEmail;
use App\Models\MailLog;
use App\Models\MailTemplate;
use Illuminate\Support\Facades\Mail;

class MailService
{
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
            return;
        }

        if ($template->requires_pro && ! isProAvailable()) {
            return;
        }

        $rendered = $template->render($data);
        $layout = settings('mail_layout', '{content}');
        $htmlContent = str_replace('{content}', $rendered['content'], $layout);

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
        $layout = settings('mail_layout', '{content}');

        return str_replace('{content}', $rendered['content'], $layout);
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
        ];
    }
}
