<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class InAppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload)
    {
        $this->onQueue('default');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! (bool) settings('notifications_enabled', true)) {
            return [];
        }

        $channels = ['database'];
        if (in_array(settings('notifications_driver', 'reverb'), ['reverb', 'pusher'], true)) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->payload['title'] ?? translate('Notification'),
            'message' => $this->payload['message'] ?? '',
            'category' => $this->payload['category'] ?? 'system',
            'level' => $this->payload['level'] ?? 'info',
            'icon' => $this->payload['icon'] ?? null,
            'action_url' => $this->payload['action_url'] ?? null,
            'action_label' => $this->payload['action_label'] ?? null,
            'meta' => $this->payload['meta'] ?? [],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
