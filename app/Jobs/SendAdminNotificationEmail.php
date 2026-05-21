<?php

namespace App\Jobs;

use App\Models\MailLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAdminNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, string|null>  $payload
     */
    public function __construct(
        private readonly int $userId,
        private readonly array $payload,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $subject = (string) ($this->payload['title'] ?? translate('Notification'));
        $message = (string) ($this->payload['message'] ?? '');
        $actionUrl = $this->payload['action_url'] ?? null;
        $actionLabel = ($this->payload['action_label'] ?? null) ?: translate('Open');
        $html = $this->html($user, $message, $actionUrl, $actionLabel);

        try {
            Mail::html($html, function ($mail) use ($user, $subject) {
                $mail->to($user->email, $user->name)->subject($subject);
            });

            MailLog::create([
                'template_slug' => 'admin_notification',
                'recipient_email' => $user->email,
                'subject' => $subject,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            MailLog::create([
                'template_slug' => 'admin_notification',
                'recipient_email' => $user->email,
                'subject' => $subject,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'sent_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function html(User $user, string $message, ?string $actionUrl, ?string $actionLabel): string
    {
        $safeMessage = nl2br(e($message));
        $appName = e(settings('app_name', 'Application'));
        $userName = e($user->name);
        $button = '';

        if ($actionUrl) {
            $safeUrl = e($actionUrl);
            $safeLabel = e($actionLabel ?: translate('Open'));
            $button = <<<HTML
                <p style="margin-top:24px">
                    <a href="{$safeUrl}" style="display:inline-block;background:#10b981;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600">{$safeLabel}</a>
                </p>
            HTML;
        }

        return <<<HTML
            <div style="font-family:Inter,Arial,sans-serif;color:#111827;line-height:1.6">
                <p>{$userName},</p>
                <p>{$safeMessage}</p>
                {$button}
                <p style="margin-top:32px;color:#6b7280;font-size:13px">{$appName}</p>
            </div>
        HTML;
    }
}
