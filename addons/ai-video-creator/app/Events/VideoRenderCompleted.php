<?php

namespace Addons\AiVideoCreator\Events;

use Addons\AiVideoCreator\Models\VcRender;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoRenderCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public VcRender $render) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->render->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'VideoRenderCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'render_id' => $this->render->id,
            'ulid' => $this->render->ulid,
            'status' => 'completed',
            'title' => $this->render->title,
        ];
    }
}
