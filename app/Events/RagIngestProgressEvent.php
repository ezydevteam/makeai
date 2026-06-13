<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RagIngestProgressEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionUlid,
        public int $userId,
        public string $status,
        public int $progress,
        public string $stage,
        public ?string $error = null,
        public ?array $sourceMeta = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('rag.session.'.$this->sessionUlid)];
    }

    public function broadcastAs(): string
    {
        return 'rag.ingest.progress';
    }
}
