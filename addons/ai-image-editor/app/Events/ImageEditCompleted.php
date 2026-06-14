<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Events;

use Addons\AiImageEditor\Models\IeEdit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImageEditCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public IeEdit $edit)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->edit->user_id);
    }

    public function broadcastAs(): string
    {
        return 'ImageEditCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'edit_id' => $this->edit->id,
            'edit_ulid' => $this->edit->ulid,
            'operation' => $this->edit->operation,
            'status' => $this->edit->status,
            'output_url' => $this->edit->output_url,
            'error' => $this->edit->error_message,
            'version' => $this->edit->version_number,
        ];
    }
}
