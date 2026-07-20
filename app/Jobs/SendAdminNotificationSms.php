<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAdminNotificationSms implements ShouldQueue
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
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        // The gateway may have been disabled, or the phone unverified/cleared, between
        // the admin clicking send and the job running — fail closed rather than error.
        if (! user_can_receive_sms($user)) {
            return;
        }

        $sms = SmsService::fromSettings();
        $e164 = PhoneNumber::e164($user->phone, $user->phone_country);
        if ($e164 === null) {
            return;
        }

        $title = trim((string) ($this->payload['title'] ?? ''));
        $body = trim((string) ($this->payload['message'] ?? ''));
        $message = $title !== '' && $body !== '' ? $title."\n\n".$body : $title.$body;

        $sms->send($e164, $message);
    }
}
