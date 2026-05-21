<?php

namespace App\Jobs;

use App\Notifications\InAppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class SendInAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly Model $notifiable,
        private readonly array $payload
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (! (bool) settings('notifications_enabled', true)) {
            return;
        }

        NotificationFacade::sendNow($this->notifiable, new InAppNotification($this->payload));
    }
}
