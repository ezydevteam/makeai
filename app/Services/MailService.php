<?php

namespace App\Services;

use App\Jobs\SendTemplatedEmail;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Send a templated email (Dispatches to queue by default)
     */
    public function send(string $slug, string $to, array $data = [], bool $queue = true): void
    {
        if ($queue) {
            SendTemplatedEmail::dispatch($slug, $to, $data);

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

        $rendered = $template->render($data);
        $layout = Setting::getValue('mail_layout', '{content}');
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
            return 'Template not found';
        }

        $rendered = $template->render($data);
        $layout = Setting::getValue('mail_layout', '{content}');

        return str_replace('{content}', $rendered['content'], $layout);
    }
}
