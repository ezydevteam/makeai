<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCreditAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected User $user,
    ) {
        $this->onQueue('mail');
    }

    public function handle(MailService $mailService): void
    {
        $mailService->processSend('credits_low', $this->user->email, [
            'user_name' => $this->user->name,
            'credits' => (int) $this->user->credits,
            'top_up_url' => url('/billing/credits'),
            'app_name' => settings('app_name', config('app.name')),
        ]);
    }
}
